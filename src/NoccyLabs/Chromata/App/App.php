<?php

namespace NoccyLabs\Chromata\App;

use NoccyLabs\Chromata\Html\Dom\Element;
use NoccyLabs\Chromata\Html\Dom\Event;

abstract class App
{


    abstract protected function create();

    abstract protected function destroy();

    abstract protected function update();

    private $is_running = false;

    public function __construct()
    {
        stream_set_blocking(STDIN,false);
        $this->create();
    }

    public function __destruct()
    {
        $this->destroy();
    }

    public final function onSignal($signo)
    {
        $this->is_running = false;
    }

    public final function startApplication()
    {
        self::info("Starting application");
        pcntl_signal(SIGINT, [$this,"onSignal"]);
        $this->is_running = true;
        $next_update = 0;
        $iv_update = 0.1;
        $sleep_time = 0.05;
        $last_ret = null;
        while ($this->is_running) {
            ob_start();
            $ts = microtime(true);
            if ($ts > $next_update) {
                $ret = $this->update();
                if ($ret != $last_ret) {
                    $last_ret = $ret;
                    if (is_numeric($ret)) {
                        $iv_update = floatval($ret);
                        $sleep_time = max($iv_update/4,0.05);
                        self::debug("App requested updated timing values: iv=%.2f, d=%.2f", $iv_update, $sleep_time);
                    } elseif (false === $ret) {
                        $this->is_running = false;
                    }
                }
                $next_update = $ts + $iv_update;
            }
            $out = ob_get_contents(); ob_end_clean(); if ($out) self::error("%s", $out);
            $this->handleOutput();
            ob_start();
            $this->handleInput();
            $out = ob_get_contents(); ob_end_clean(); if ($out) self::error("%s", $out);
            pcntl_signal_dispatch();
            usleep($sleep_time*1000000);
        }

    }

    private function handleOutput()
    {
        $script = $this->root->getUpdate();
        if (!$script) return;
        fprintf(STDOUT,"%s\n", $script);
    }

    private function handleInput()
    {
        $line = fread(STDIN, 8192);
        if (!trim($line)) return;
        $lines = explode("\n",$line);
        foreach ($lines as $line) {
            if (!trim($line)) continue;
            list($type,$data)=explode(":",trim($line),2);
            if ($type == 'event') {
                $this->handleDomEvent(json_decode($data));
            } else {
                self::warn("invalid message received type=%s, data=%s", $type, $data);
            }
        }
    }

    private function handleDomEvent($event)
    {
        $id = $event->target->id;
        $elem = $this->root->findById($id);
        if (!$elem) {
            self::warn("Can't send event to element with id=%s: no such element", $id);
        }
        $event = new Event($elem, $event->type, (array)$event->data);
        $elem->onEvent($event);
    }

    protected function setRoot(Element $elem)
    {
        $this->root = $elem;
    }

    protected static function log($level, $style, $fmt, ...$args)
    {
        if (($fmt) && (posix_isatty(STDERR)))
            $fmt = $style.$fmt."\e[0m";
        fprintf(STDERR, "%5s: {$fmt}\n", $level, ...$args);
    }

    protected static function debug($fmt, ...$args)
    { self::log('debug',"\e[32m",$fmt,...$args); }
    protected static function info($fmt, ...$args)
    { self::log('info',"\e[32;1m",$fmt,...$args); }
    protected static function warn($fmt, ...$args)
    { self::log('warn',"\e[33;1m",$fmt,...$args); }
    protected static function error($fmt, ...$args)
    { self::log('error',"\e[31;1m",$fmt,...$args); }

}