<?php

require_once __DIR__."/../vendor/autoload.php";
define("APP", "wsproxy");

// I am a websocket server!

$signal = false;
pcntl_signal(SIGINT, function ($val) use (&$signal) {
    $signal = $val;
});

l_info("Starting up...");
$wsproxy = new NoccyLabs\Chromata\WebSocket\WsProxy();
try {
    $wsproxy->boot();
    while (false === $signal) {
        $wsproxy->update();
        pcntl_signal_dispatch();
        usleep(100000);
    }
} catch (\Exception $e) {
    l_error("Unhandled exception: %s", $e->getMessage());
    $wsproxy->terminate();
    exit(1);
}
$wsproxy->terminate();