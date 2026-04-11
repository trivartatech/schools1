<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// DEBUG: Log EVERY request for a short period to find the silent robot
$logReq = true; // Always log for now
if ($logReq) {
    $headers = getallheaders();
    $headerStr = '';
    foreach ($headers as $name => $value) {
        $headerStr .= "$name: $value; ";
    }
    @file_put_contents(
        __DIR__.'/../storage/logs/request_trace.log', 
        date('[Y-m-d H:i:s] ') . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' UA: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'None') . ' IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . ' HEADERS: ' . $headerStr . PHP_EOL, 
        FILE_APPEND
    );
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
