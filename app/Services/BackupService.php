<?php

namespace App\Services;

use App\Models\BackupLog;

class BackupService
{
    public function create(int $schoolId, int $userId, string $label = ''): BackupLog
    {
        $startTime = microtime(true);

        $log = BackupLog::create([
            'school_id'  => $schoolId,
            'created_by' => $userId,
            'label'      => $label ?: 'Manual Backup',
            'status'     => 'running',
            'size_bytes' => 0,
        ]);

        try {
            $dir = storage_path("app/backups/{$schoolId}");
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'backup_' . date('Ymd_His') . '_' . $log->id . '.sql.gz';
            $filepath = "{$dir}/{$filename}";

            $this->runMysqldump($filepath);

            $duration = (int) round(microtime(true) - $startTime);
            $size = file_exists($filepath) ? filesize($filepath) : 0;

            $log->update([
                'filename'         => $filename,
                'status'           => 'completed',
                'size_bytes'       => $size,
                'duration_seconds' => $duration,
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $log->fresh();
    }

    public function delete(BackupLog $log): void
    {
        $path = $log->getFilePath();
        if ($log->filename && file_exists($path)) {
            unlink($path);
        }
        $log->delete();
    }

    public function getStorageUsed(int $schoolId): string
    {
        $dir = storage_path("app/backups/{$schoolId}");
        if (!is_dir($dir)) {
            return '0 B';
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        if ($size >= 1073741824) return round($size / 1073741824, 2) . ' GB';
        if ($size >= 1048576)    return round($size / 1048576, 2) . ' MB';
        if ($size >= 1024)       return round($size / 1024, 2) . ' KB';
        return $size . ' B';
    }

    private function runMysqldump(string $filepath): void
    {
        $config = config('database.connections.' . config('database.default'));

        if (($config['driver'] ?? '') !== 'mysql') {
            throw new \RuntimeException('MySQL database connection required for backup.');
        }

        $host = escapeshellarg($config['host'] ?? '127.0.0.1');
        $port = escapeshellarg($config['port'] ?? '3306');
        $user = escapeshellarg($config['username']);
        $db   = escapeshellarg($config['database']);
        $pass = !empty($config['password']) ? '-p' . escapeshellarg($config['password']) : '';
        $out  = escapeshellarg($filepath);

        $cmd = "mysqldump -h {$host} -P {$port} -u {$user} {$pass} {$db} | gzip > {$out}";

        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('mysqldump failed: ' . implode(' ', $output));
        }
    }
}
