<?php

use IsaEken\PackagistMirror\Application;
use IsaEken\PackagistMirror\Config;
use IsaEken\PackagistMirror\Router;

require_once __DIR__ . "/../vendor/autoload.php";

unset($argv[0]);

$app = new Application;
$app->config = new Config;
$app->router = new Router($argv);
return $app->run();
