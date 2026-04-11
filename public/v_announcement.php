<?php
/**
 * Standalone ExoML Fetcher - v21 (Zero-Dependency)
 * High-performance pure PHP to bypass framework hangups.
 */

// 1. HEAD request handler (Exotel verification)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    header("Content-Type: text/xml; charset=utf-8");
    exit;
}

// 2. Logging
$logFile = __DIR__ . '/../storage/logs/robot_debug.log';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'None';
$say = $_GET['s'] ?? '';
$audios = isset($_GET['a']) ? (is_array($_GET['a']) ? $_GET['a'] : [$_GET['a']]) : [];

$msg = date('[Y-m-d H:i:s] ') . "🎯 v21 HIT: $method IP: $ip UA: $ua MESSAGE: " . substr($say, 0, 50) . PHP_EOL;
@file_put_contents($logFile, $msg, FILE_APPEND);

// 3. Build Response
header("Content-Type: text/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<Response>' . PHP_EOL;

if (!empty($say) || !empty($audios)) {
    foreach ($audios as $audio) {
        if (!empty($audio)) echo '    <Play>' . htmlspecialchars($audio) . '</Play>' . PHP_EOL;
    }
    if (!empty($say)) {
        echo '    <Say voice="female" language="en-IN">' . htmlspecialchars($say) . '</Say>' . PHP_EOL;
    }
} else {
    echo '    <Say voice="female" language="en-IN">Welcome. No announcement content was provided.</Say>' . PHP_EOL;
}
echo '</Response>';
exit;
