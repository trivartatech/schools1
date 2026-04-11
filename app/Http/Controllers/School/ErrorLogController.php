<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class ErrorLogController extends Controller
{
    private const PER_PAGE = 50;
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB tail read limit

    public function index(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');

        $allEntries = file_exists($logPath) ? $this->parseLogFile($logPath) : [];

        $availableLevels = array_values(array_unique(array_column($allEntries, 'level')));

        // Apply filters
        $level  = strtoupper($request->input('level', ''));
        $search = $request->input('search', '');
        $date   = $request->input('date', '');

        $filtered = $allEntries;

        if ($level) {
            $filtered = array_filter($filtered, fn($e) => $e['level'] === $level);
        }
        if ($search) {
            $filtered = array_filter($filtered, fn($e) => stripos($e['message'], $search) !== false);
        }
        if ($date) {
            $filtered = array_filter($filtered, fn($e) => str_starts_with($e['datetime'], $date));
        }

        $filtered = array_values($filtered);

        $page    = max(1, (int) $request->input('page', 1));
        $total   = count($filtered);
        $offset  = ($page - 1) * self::PER_PAGE;

        $paginator = new LengthAwarePaginator(
            array_slice($filtered, $offset, self::PER_PAGE),
            $total,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('School/Utility/ErrorLog', [
            'logs'            => $paginator,
            'filters'         => $request->only(['level', 'search', 'date']),
            'availableLevels' => $availableLevels,
            'logSize'         => file_exists($logPath) ? $this->formatBytes(filesize($logPath)) : '0 B',
            'totalEntries'    => count($allEntries),
        ]);
    }

    private function parseLogFile(string $path): array
    {
        $content = $this->readTail($path);
        if (!$content) return [];

        // Match Laravel log lines: [datetime] env.LEVEL: message
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)/m';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $entries = [];
        $count   = count($matches);

        for ($i = 0; $i < $count; $i++) {
            $m = $matches[$i];

            // Grab everything between this log line and the next as the stack trace
            $bodyStart  = $m[0][1] + strlen($m[0][0]);
            $bodyEnd    = isset($matches[$i + 1]) ? $matches[$i + 1][0][1] : strlen($content);
            $stackTrace = trim(substr($content, $bodyStart, $bodyEnd - $bodyStart));

            $entries[] = [
                'id'          => $i,
                'datetime'    => $m[1][0],
                'env'         => $m[2][0],
                'level'       => strtoupper($m[3][0]),
                'message'     => $m[4][0],
                'stack_trace' => $stackTrace ?: null,
            ];
        }

        return array_reverse($entries); // newest first
    }

    private function readTail(string $path): string
    {
        $size = filesize($path);
        if ($size === 0) return '';

        $bytesToRead = min($size, self::MAX_BYTES);
        $handle      = fopen($path, 'rb');
        fseek($handle, -$bytesToRead, SEEK_END);
        $content = fread($handle, $bytesToRead);
        fclose($handle);

        // Drop the possibly-truncated first line when we didn't start from the beginning
        if ($bytesToRead < $size) {
            $nl = strpos($content, "\n");
            if ($nl !== false) {
                $content = substr($content, $nl + 1);
            }
        }

        return $content;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return "{$bytes} B";
    }
}
