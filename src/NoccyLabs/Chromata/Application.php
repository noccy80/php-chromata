<?php

namespace NoccyLabs\Chromata;

use NoccyLabs\Chromata\Object\ObjectManager;

class Application
{
    /** @var TaskManager $tasks The task manager running the services */
    protected $tasks;

    protected $objects;
    /** @var int|null $signal The signal received if any */
    protected $signal;
    /** @var int $baseport The base port number, should be dividable by 5 (9100,9105,...) */
    protected $baseport = 9300;

    protected $ipc_endpoint;

    protected $ipc_socket;

    protected $ipc_clients = [];

    protected $ipc_map = [];

    protected $app_map = [];

    protected $apps = [];

    public function __construct()
    {
        $this->tasks = new TaskManager();
        $this->objects = new ObjectManager();
        pcntl_signal(SIGINT, [ $this, "on_signal" ]);
    }

    protected function got_signal() 
    { 
        return ($this->signal !== null);
    }

    public function on_signal($sig)
    {
        $this->signal = $sig;
    }

    public function run()
    {

        $container = 'none'; // webapp-container';

        $opts = getopt("hp:u:",["help","port:","ui:"]);
        foreach ($opts as $opt=>$value) switch ($opt) {
            case 'h': case 'help':

                break;
            case 'p': case 'port':
                if (($value<4000)||($value>65535)) {
                    l_error("Invalid port number %d (should be 4000-65535)", $value);
                    exit(1);
                }
                $this->baseport = intval($value);
                break;
            case 'u': case 'ui':
                $container = $value;
                break;
        }

        define("ROOT", dirname(dirname(dirname(__DIR__))));

        $port_www = $this->baseport;
        $port_wsd = $this->baseport + 1;
        $port_ipc = $this->baseport + 2;

        $this->bindIpc("127.0.0.1:{$port_ipc}");

        $env = [
            "SESSION_URI" => "127.0.0.1:{$port_wsd}",
            "CHROMATA_HOST" => "127.0.0.1",
            "CHROMATA_BASEPORT" => $this->baseport
        ];

        $this->tasks->createTask("httpd", "php -S 127.0.0.1:{$port_www}", TASK_SERVICE | TASK_RESTART, ROOT."/web", $env);
        $this->tasks->createTask("chromata-ws", "chromata-ws --bind 127.0.0.1:{$port_wsd} --ipc 127.0.0.1:{$port_ipc}", TASK_SERVICE | TASK_RESTART, ROOT."/src");
        $this->setupUi($container, $port_www);

        l_info("The application is listening on http://127.0.0.1:%d", $this->baseport);

        /*
        // TIMERS!
        l_debug("Testing Timer");
        $t1 = new Utility\Timer(function () { l_debug("one-shot"); }, 1000, Utility\Timer::TIMER_ONE_SHOT);
        $t2 = new Utility\Timer(function () { l_debug("repeat"); }, 1000);
        $this->objects->add($t1);
        $this->objects->add($t2);
        */

        try {
            while (!$this->got_signal()) {
                // Refresh tasks and objects
                $this->tasks->refresh();
                $this->objects->refresh();
                foreach ($this->apps as $app) {
                    $this->handleApp($app);
                }
                $this->handleIpc();
                // Sleep for a bit, to decrease cpu load
                usleep(10000);
                // Check if a master task has exited, bail if so. Otherwise
                // dispatch any signals if needed.
                if ($this->tasks->canExit()) { break; }
                pcntl_signal_dispatch();
            }
        } catch (\Exception $e) {
            l_error("Exception: %s", $e->getMessage());
            if (getenv("DEBUG")) {
                $estr = explode("\n",$e);
                foreach ($estr as $str) l_warn($str);
            }
        }

        $this->tasks->terminate();
    }

    private function setupUi($container, $port_www)
    {
        switch ($container) {
            case 'webapp-container':
                l_debug("Using webapp-container as frontend");
                $env = [ "APP_ID" => "chromata" ];
                $this->tasks->createTask("ui", "webapp-container --webappUrlPatterns=http://127.0.0.1:{$port_www}/* --homepage http://127.0.0.1:{$port_www} --store-session-cookies", TASK_MASTER|TASK_MUTE_ERR, null, $env);
                break;
            case 'google-chrome':
            case 'chrome':
                l_debug("Using google-chrome as frontend");
                //l_warn("You need to terminate chromata manually after closing the window, as chrome exits immediately when invoked");
                $this->tasks->createTask("chrome", "google-chrome --app=http://127.0.0.1:{$port_www}");
                break;
            case 'none':
                break;
            default:
                l_error("Invalid frontend container: %s", $container);
                throw new \Exception("Couldn't find the specified frontend task {$container}");
        }
    }

    protected function handleApp($app)
    {
        $read = $write = $except = $app->pipes;
        if (stream_select($read, $write, $except, 0)) {
            if (in_array($app->pipes[1], $read, true)) {
                $data = fread($app->pipes[1],8192);
                $this->sendIpc($app->peer,"update",['data'=>trim($data)]);
            }
            if (in_array($app->pipes[2], $read, true)) {
                $log = fread($app->pipes[2],8192);
                $log = explode("\n",$log);
                foreach ($log as $line) {
                    if ($line) l_debug("chromata-app: %s", $line);
                }
            }
        }
    }

    public function writeApp($peer, $data)
    {
        foreach ($this->apps as $app) {
            if ($app->peer === $peer) {
                fwrite($app->pipes[0], $data."\n");
                return true;
            }
        }
        l_warn("Unable to write to app for peer %s", $peer);
    }

    protected function createApp($peer)
    {
        l_debug("Creating new application for %s", $peer);
        $dir = escapeshellarg(ROOT."/app");
        $app_ds = [ 0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w'] ];
        $app = proc_open("chromata-app --appdir {$dir}", $app_ds, $app_pipes);
        $this->apps[] = (object)[
            'proc' => $app,
            'pipes' => $app_pipes,
            'peer' => $peer
        ];
    }

    protected function destroyApp($peer)
    {
        l_debug("Destroying application for %s", $peer);
        foreach ($this->apps as $id=>$app) {
            if ($app->peer == $peer) {
                proc_terminate($app->proc);
                proc_close($app->proc);
                unset($this->apps[$id]);
            }
        }
    }

    protected function handleIpc()
    {
        if (!$this->ipc_socket) {
            throw new \Exception("No IPC socket opened");
        }
        $read = array_merge([ $this->ipc_socket ], array_values($this->ipc_clients));
        $write = [];
        $except = [];
        if (stream_select($read, $write, $except, 0)) {
            foreach ($read as $socket) {
                if ($socket == $this->ipc_socket) {
                    $sock = stream_socket_accept($socket, null, $peer);
                    l_info("New IPC connection from peer %s", $peer);
                    $this->ipc_clients[$peer] = $sock;
                } else {
                    $fc = 0;
                    if (($msg = fgets($socket))) {
                        $fc++;
                        $this->readIpc(trim($msg),$socket);
                    }
                    if ($fc == 0) {
                        $id = stream_socket_get_name($socket, true);
                        l_warn("IPC Client disconnected: %s", $id);
                        fclose($socket);
                        unset($this->ipc_clients[$id]);
                    }
                }
            }
        }
    }

    protected function readIpc($raw, $socket)
    {
        if (strpos($raw,"|")===false) {
            l_warn("Invalid IPC frame received");
            return;
        }
        list ($type,$data) = explode("|",trim($raw),2);
        $data = (object)json_decode($data);
        switch ($type) {
            case 'client.connect':
                $peer = stream_socket_get_name($socket, true);
                l_info("Client connected: %s", $data->peer);
                $this->ipc_map[$data->peer] = $peer;
                $this->app_map[$peer] = $data->peer;
                $id = md5($data->peer);
                $this->sendIpc($data->peer, "session.open", [ "id"=>$id ]);
                $this->createApp($data->peer, $id);                
                break;
            case 'client.disconnect':
                l_info("Client disconnected: %s", $data->peer);
                $this->destroyApp($data->peer);
                break;
            case 'event':
                $peer = $this->app_map[stream_socket_get_name($socket, true)];
                $this->writeApp($peer, "event:".json_encode($data));
                break;
            default:
                l_warn("Invalid IPC frame type: %s", $type);
        }
    }

    protected function sendIpc($peer, $type, array $params)
    {
        if (!array_key_exists($peer, $this->ipc_map)) {
            l_warn("No such peer connected to IPC: %s", $peer);
            return;
        }
        $mapped = $this->ipc_map[$peer];
        fwrite($this->ipc_clients[$mapped], $type."|".json_encode($params)."\n");
    }

    protected function bindIpc($endpoint) {
        l_info("Binding IPC socket at %s", $endpoint);
        $server = stream_socket_server("tcp://{$endpoint}", $errno, $errstr);
        if ($errno) {
            throw new \Exception("Unable to bind ipc endpoint: {$errno} {$errstr}");
        }
        $this->ipc_endpoint = $endpoint;
        $this->ipc_socket = $server;
    }

    public function terminate()
    {
        l_info("Terminating apphost");
        if ($this->ipc_socket) {
            fclose($this->ipc_socket);
        }
    }
    
}

