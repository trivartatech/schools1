<?php
/**
 * Fix v7: Innocent Proxy Bypass
 * Serves XML content from .txt files to bypass WAF User-Agent blocks.
 */

$id = $_GET['f'] ?? '';
$logFile = __DIR__ . '/../storage/logs/robot_debug.log';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'None';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Log the hit for debugging
$log = date('[Y-m-d H:i:s] ') . "🎯 PROXY HIT: File [$id] IP: $ip UA: $ua" . PHP_EOL;
@file_put_contents($logFile, $log, FILE_APPEND);

// Only allow files following our pattern: v_MOBILE_UUID.txt
if (preg_match('/^v_[0-9]+_[a-f0-9-]+\.txt$/', $id) && file_exists(__DIR__ . '/' . $id)) {
    header("Content-Type: text/xml; charset=utf-8");
    echo file_get_contents(__DIR__ . '/' . $id);
} else {
    header("HTTP/1.0 404 Not Found");
    echo '<?xml version="1.0" encoding="UTF-8"?><Response><Say>File not found</Say></Response>';
}
exit;
