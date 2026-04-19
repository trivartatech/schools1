<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function __construct(private BackupService $backupService) {}

    public function index()
    {
        $schoolId = app('current_school_id');

        $backups = BackupLog::tenant()
            ->with('createdByUser:id,name')
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'              => $b->id,
                'label'           => $b->label,
                'filename'        => $b->filename,
                'status'          => $b->status,
                'formatted_size'  => $b->formatted_size,
                'duration_seconds' => $b->duration_seconds,
                'created_by'      => $b->createdByUser?->name,
                'created_at'      => $b->created_at?->format('d M Y, H:i'),
                'file_exists'     => $b->fileExists(),
                'error_message'   => $b->error_message,
            ]);

        $stats = [
            'total'        => BackupLog::tenant()->count(),
            'completed'    => BackupLog::tenant()->where('status', 'completed')->count(),
            'failed'       => BackupLog::tenant()->where('status', 'failed')->count(),
            'last_backup'  => BackupLog::tenant()->where('status', 'completed')->latest()->value('created_at')?->diffForHumans() ?? 'Never',
            'storage_used' => $this->backupService->getStorageUsed($schoolId),
        ];

        return Inertia::render('School/Backup/Index', [
            'backups' => $backups,
            'stats'   => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'nullable|string|max:200',
        ]);

        $schoolId = app('current_school_id');

        try {
            $this->backupService->create($schoolId, auth()->id(), $request->label ?? '');
            return back()->with('success', 'Backup created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function download(BackupLog $backup)
    {
        abort_unless($backup->school_id === app('current_school_id'), 403);
        abort_unless($backup->status === 'completed' && $backup->fileExists(), 404, 'Backup file not found.');

        return response()->download($backup->getFilePath(), $backup->filename);
    }

    public function destroy(BackupLog $backup)
    {
        abort_unless($backup->school_id === app('current_school_id'), 403);

        $this->backupService->delete($backup);

        return back()->with('success', 'Backup deleted.');
    }
}
