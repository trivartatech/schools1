<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class StaffExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $query = Staff::tenant()->with(['user', 'department', 'designation']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $statusFilter = $request->query('status', 'current');
        if ($statusFilter === 'current') {
            $query->whereIn('status', ['active', 'on_leave']);
        } else {
            $query->whereIn('status', ['inactive', 'resigned', 'terminated']);
        }

        $staff = $query->latest()->get();

        $headers = ['S.No', 'Employee ID', 'Name', 'Email', 'Phone', 'Department', 'Designation', 'Joining Date', 'Qualification', 'Status'];

        $rows = [];
        foreach ($staff as $i => $s) {
            $rows[] = [
                $i + 1,
                $s->employee_id,
                $s->user?->name ?? '',
                $s->user?->email ?? '',
                $s->user?->phone ?? '',
                $s->department?->name ?? '',
                $s->designation?->name ?? '',
                $s->joining_date,
                $s->qualification ?? '',
                ucfirst($s->status),
            ];
        }

        return $this->exportResponse($request, $headers, $rows, 'staff-export-' . now()->format('Y-m-d'));
    }
}
