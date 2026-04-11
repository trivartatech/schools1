<?php
/**
 * Standalone Fetch Diagnostic
 * Bypasses Laravel to see if the robot can reach the server at all.
 */
$logFile = __DIR__ . '/../storage/logs/robot_debug.log';
$id = $_GET['id'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'None';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$headers = getallheaders();
$headerStr = "";
foreach ($headers as $k => $v) { $headerStr .= "$k: $v; "; }

$msg = date('[Y-m-d H:i:s] ') . "HOST: {$_SERVER['HTTP_HOST']} METHOD: $method ID: $id UA: $ua IP: $ip HEADERS: $headerStr" . PHP_EOL;
@file_put_contents($logFile, $msg, FILE_APPEND);

// If it's a HEAD request (Exotel often does this first), return 200 immediately
if ($method === 'HEAD') {
    header("Content-Type: text/xml");
    exit;
}

// Return a simple, valid Exoml response
header("Content-Type: text/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="female" language="en-IN">Diagnostics successful. This is a direct script response. If you hear this, then your server is not blocking robots, and we simply need to fix the Laravel cache lookup.</Say>
</Response>';
