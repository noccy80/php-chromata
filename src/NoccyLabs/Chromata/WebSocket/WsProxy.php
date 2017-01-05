<?php

namespace NoccyLabs\Chromata\WebSocket;

class WsProxy
{
    protected $ipc_endpoint = null;

    protected $ipc_socket = null;

    protected $ws_endpoint = null;

    protected $ws_socket = null;

    protected $ws_clients = null;

    public function __construct()
    {
        $this->ws_clients = [];
    }

    public function boot()
    {
        l_info("Booting wsproxy");
        $args = getopt("b:",["bind:","ipc:"]);
        if (empty($args['bind'])) {
            throw new \Exception("No endpoint specified to --bind to");
        }
        if (empty($args['ipc'])) {
            l_warn("IPC not connected, activating echo mode!");
        } else {
            l_debug("Connecting to application server %s", $args['ipc']);
            $this->connectIpc($args['ipc']);
        }

        l_debug("Creating HTTP server on %s", $args['bind']);
        $this->bindHttp($args['bind']);
    }

    protected function bindHttp($endpoint) {
        $server = stream_socket_server("tcp://{$endpoint}", $errno, $errstr);
        if ($errno) {
            l_error("Error when trying to listen on %s: %d %s", $endpoint, $errno, $errstr);
            throw new \Exception("Unable to bind http/ws endpoint: {$errno} {$errstr}");
        }
        $this->ws_endpoint = $endpoint;
        $this->ws_socket = $server;
    }

    protected function connectIpc($endpoint) {
        $socket = stream_socket_client("tcp://{$endpoint}", $errno, $errstr);
        if ($errno) {
            l_error("Error when trying to connect to %s", $endpoint);
            throw new \Exception("Unable to connect to ipc endpoint: {$errno} {$errstr}");
        }
        $this->ipc_endpoint = $endpoint;
        $this->ipc_socket = $socket;
    }

    public function terminate()
    {
        l_info("Terminating wsproxy");
        if ($this->ipc_socket) {
            fclose($this->ipc_socket);
        }
    }

    public function update()
    {
        $read = [ $this->ws_socket ];
        if ($this->ipc_socket) { $read[] = $this->ipc_socket; }
        foreach ($this->ws_clients as $index=>$client) {
            if (($socket = $client->getSocket())) {
                $read[] = $socket;
            } else {
                l_info("Discarding client %s due to error", $client->getPeer());
                $this->ws_clients[$index] = null;
            } 
        }
        $write = [];
        $except = [];
        if (stream_select($read, $write, $except, 0)) {
            foreach ($read as $socket) {
                $this->handleSocketRead($socket);
            }
        }        
    }

    private function handleSocketRead($socket)
    {
        if ($socket === $this->ws_socket) {
            $csocket = stream_socket_accept($socket, null, $peername);
            l_info("Accepted connection from %s", $peername);
            $cdata = new Client($peername, $csocket, $this);
            $this->ws_clients[] = $cdata;
        } elseif ($socket === $this->ipc_socket) {
            $pc = 0;
            if (($read = fread($socket, 8192))) {
                $pc++;
                $this->readIpcMessage($read);
            }
            if ($pc == 0) {
                l_error("IPC connection lost");
                $this->ipc_socket = null;
            }
        } else {
            foreach ($this->ws_clients as $ckey=>$c) {
                if ($c->hasSocket($socket)) { $client=$c; $cid=$ckey; break; }
            }
            $read = fread($socket, 8192);
            if ($read == null) {
                l_info("Client disconnect");
                unset($this->ws_clients[$cid]);
                return;
            }
            if ($client->read($read) === false) {
                l_info("Discarding client %s", $client->getPeer());
                unset($this->ws_clients[$cid]);
            }
        }
    }

    protected function readIpcMessage($raw)
    {
        if (strpos($raw,"|")===false) {
            l_warn("Invalid IPC frame received");
            return;
        }
        list ($type,$data) = explode("|",$raw,2);
        $data = (object)json_decode($data);
        switch ($type) {
            case 'session.open':
                l_info("Server opened new session (id=%s)", $data->id);
                break;
            case 'update':
                if (empty($data->data)) {
                    l_warn("bad update frame: %s", json_encode($data));
                    continue;
                }
                foreach ($this->ws_clients as $client) {
                    $client->write("update|".$data->data);
                }
                break;
            default:
                l_warn("Invalid IPC frame type: %s", $type);
        }
    }

    public function sendIpcMessage($type, array $params)
    {
        if (!$this->ipc_socket) return false;
        fwrite($this->ipc_socket, $type."|".json_encode($params)."\n");
    }
}
