<?php

require_once __DIR__."/../vendor/autoload.php";
define("APP", "apphost");

$signal = false;
pcntl_signal(SIGINT, function ($val) use (&$signal) {
    $signal = $val;
});

$opts = getopt("h",[ "appdir:" ]);
if (empty($opts['appdir'])) {
    l_error("No --appdir specified!");
    exit(1);
}

$app_dir = $opts['appdir'];
$app_src = $app_dir."/src/main.php";

$class = require_once $app_src;
$inst = new $class;
$inst->startApplication();

/*

$apphost = new NoccyLabs\Chromata\App\AppHost();
try {
    $apphost->boot();
    while (false === $signal) {
        $apphost->update();
        pcntl_signal_dispatch();
        usleep(100000);
    }
} catch (\Exception $e) {
    l_error("Unhandled exception: %s", $e->getMessage());
    $apphost->terminate();
    exit(1);
}
$apphost->terminate();
*/