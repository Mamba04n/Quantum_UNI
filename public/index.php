<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = realpath(__DIR__ . '/..');
if (! file_exists($basePath . '/vendor/autoload.php') && file_exists('/home/u806977200/apps/QuantumComplete/current/vendor/autoload.php')) {
    $basePath = '/home/u806977200/apps/QuantumComplete/current';
}

if (file_exists($maintenance = $basePath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
