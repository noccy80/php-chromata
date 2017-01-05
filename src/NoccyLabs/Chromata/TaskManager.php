<?php

namespace NoccyLabs\Chromata;

define("TASK_DEFAULT",0x00); // Defaults, i.e. no special flags
define("TASK_SERVICE",0x01); // Service process, must start and keep running
define("TASK_RESTART",0x02); // Attempt to restart service process if it fails
define("TASK_MASTER", 0x04); // Master process, exit app on process exit
define("TASK_DISOWN", 0x08); // Forget about this process once it is started
define("TASK_THREAD", 0x10); // Threading, don't use!

class TaskManager
{
    protected $tasks = [];

    protected $canExit = false;

    public function createTask($name, $command, $flags=TASK_DEFAULT, $chdir=null, $env=null)
    {
        $task = new TaskStruct($name, $command, $flags, $chdir, $env);
        $task->start();
        $this->tasks[] = $task;
    }

    public function refresh()
    {
        foreach ($this->tasks as $task) {
            if ($task->refresh() === false) {
                $this->canExit = true;
            }
        }
    }

    public function canExit()
    {
        return $this->canExit;
    }

    public function __destruct()
    {
        $this->terminate();
    }

    public function terminate() 
    {
        foreach ($this->tasks as $task) {
            $task->terminate();
        }
        $this->tasks = [];
    }


}

class TaskStruct
{
    protected $name;

    protected $flags = 0;

    protected $proc;

    protected $command;

    protected $env;

    protected $chdir;

    protected $status;

    protected $pipes;

    protected $restarted = 0;

    public function __construct($name, $command, $flags, $chdir, $env)
    {
        $this->name = $name;
        $this->env = $env;
        $this->command = $command;
        $this->flags = $flags;
        $this->chdir = $chdir;
        $flagstr=[];
        ($flags&TASK_MASTER) && $flagstr[]='MASTER';
        ($flags&TASK_SERVICE) && $flagstr[]='SERVICE';
        ($flags&TASK_RESTART) && $flagstr[]='RESTART';
        ($flags&TASK_DISOWN) && $flagstr[]='DISOWN';
        l_info("Creating new task %s [%s]", $this->name, join(",",$flagstr));
    }

    public function start()
    {
        if ($this->proc) {
            proc_close($this->proc);
            $this->proc = null;
        }

        $ds = [
            0 => [ "pipe", "r" ],
            1 => [ "pipe", "w" ],
            2 => [ "pipe", "w" ],
        ];
        $chdir = (empty($this->chdir))?(__DIR__."/../.."):$this->chdir;
        $envs = [];
        foreach ($_SERVER as $k=>$v) {
            if ($k === strtoupper($k)) { $envs[$k] = $v; }
        }
        if (is_array($this->env)) {
            $envs = array_merge($envs, $this->env);
        }
        $proc = proc_open(
            $this->command, 
            $ds, 
            $pipes, 
            $chdir, 
            $envs
        );
        if ($proc) {
            $this->proc = $proc;
            $this->pipes = $pipes;
            $this->status = proc_get_status($proc);
            $this->refresh();
        }

        return $this->status['running'];
    }

    protected function killPid($pid, $signal, $timeout=5)
    {
        $ts = microtime(true);
        posix_kill($pid, $signal);
        while (microtime(true)<$ts+$timeout) {
            $ps = posix_kill($pid, 0);
            if ($ps == false) { 
                $this->status = null;
                return true;
            }
            usleep(10000);
        }
        return false;
    }

    protected function killProcess($proc, $signal, $timeout=5)
    {
        $ts = microtime(true);
        proc_terminate($proc, $signal);
        while (microtime(true)<$ts+$timeout) {
            $ps = proc_get_status($this->proc);
            if ($ps['running'] == false) { 
                $this->status = null;
                return true;
            }
            usleep(10000);
        }

        return false;
    }

    protected function getChildrenPids()
    {
        if (!$this->status) {
            $ps = proc_get_status($this->proc);
            $pid = $ps['pid'];
        } else {
            $pid = $this->status['pid'];
        }
        $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid {$pid}`);
        $ret = [];
        foreach($pids as $cpid) {
            if(is_numeric($cpid)) $ret[] = $cpid;
        }  
        return $ret;
        
    }

    public function terminate()
    {
        // Flush the pipes, tho this might be mostly voodoo.
        if ($this->pipes) {
            $this->read(1,65535);
            $this->read(2,65535);
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            $this->pipes = null;
        }

        if ($this->proc) {
            l_info("%s: Terminating process", $this->name);

            $pids = $this->getChildrenPids();
            foreach ($pids as $cpid) {
                if (!$this->killPid($cpid, SIGINT, 5)) {
                    if (!$this->killPid($cpid, SIGTERM, 5)) {
                        l_error("Process %s with pid %d didn't die!", $this->name, $cpid);
                    }
                }                
            }

            if (!$this->killProcess($this->proc, SIGINT, 5)) {
                if (!$this->killProcess($this->proc, SIGTERM, 5)) {
                    l_error("%s: Warning: The process failed to exit!", $this->name);
                }
            }
            
            proc_close($this->proc);
            $this->proc = null;
            $this->status = null;
        }
    }

    public function refresh()
    {
        if ($this->pipes) {
            $r = [$this->pipes[1],$this->pipes[2]];
            $w = [$this->pipes[0]];
            $e = $this->pipes;
            if (stream_select($r,$w,$e,0)) {
                if (in_array($this->pipes[2],$r,true)) {
                    stream_set_blocking($this->pipes[2],false);
                    $lines = explode("\n",fread($this->pipes[2],4096));
                    foreach ($lines as $line) if ($line) l_notice("%s: %s", $this->name, $line);
                }
                if (in_array($this->pipes[1],$r,true)) {
                    stream_set_blocking($this->pipes[1],false);
                    $lines = explode("\n",fread($this->pipes[1],4096));
                    foreach ($lines as $line) if ($line) l_debug("%s: %s", $this->name, $line);
                }
            }
        }
        if ($this->proc) {
            if (empty($this->status) || ($this->status['running'])) {
                $this->status = proc_get_status($this->proc);
            }
            if ($this->status['running'] == false) {
                if ($this->flags & TASK_MASTER) {
                    l_notice("%s exited (master process) -- shutting down", $this->name);
                    return false;
                }
                if ($this->flags & TASK_RESTART) {
                    if ($this->restarted++ > 3) {
                        throw new \Exception("The service {$this->name} crashed more than 3 times: {$this->command} (cwd={$this->chdir})");
                    } else {
                        usleep(500000);
                        l_warn("%s: Process exited with status %d, restarting...", $this->name, $this->status['exitcode']);
                    }
                    if ($this->start()) {
                        l_notice("%s: Process restarted", $this->name);
                    }
                    
                } else {
                    l_notice("%s: Process exited with code %d", $this->name, $this->status['exitcode']);
                    $this->proc = null;
                    $this->pipes = null;
                    $this->status = null;
                }
            }
        } 
    }

    public function write($pipe, $data)
    {
        $read = [];
        $write = [ $this->pipes[$pipe] ];
        $except = [];
        if (!stream_select($read, $write,$except,0 )) {
            return false;
        }
        return fwrite($this->pipes[$pipe], $data);
    }

    public function read($pipe, $length)
    {
        $read = [ $this->pipes[$pipe] ];
        $write = [];
        $except = [];
        if (!stream_select($read, $write,$except, 0)) {
            return null;
        }
        $data = fread($this->pipes[$pipe], $length);
    }

}
