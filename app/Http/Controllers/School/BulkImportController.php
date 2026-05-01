<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Imports\StudentImport;
use App\Imports\StudentBulkUpdateImport;
use App\Imports\StaffImport;
use App\Exports\StudentImportTemplate;
use App\Exports\StudentUpdateTemplate;
use App\Exports\StaffImportTemplate;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BulkImportController extends Controller
{
    protected function importTypes(): array
    {
        return [
            'students' => ['label' => 'New Students', 'icon' => 'users', 'description' => 'Import new student records from Excel'],
            'student_update' => ['label' => 'Update Students', 'icon' => 'pencil', 'description' => 'Bulk update existing student data'],
            'staff' => ['label' => 'New Staff', 'icon' => 'briefcase', 'description' => 'Import new staff members from Excel'],
            'photos' => ['label' => 'Bulk Photos', 'icon' => 'image', 'description' => 'Upload student photos by admission number'],
        ];
    }

    public function index(Request $request)
    {
        return Inertia::render('School/BulkImport/Index', [
            'importTypes' => $this->importTypes(),
            'selectedType' => $request->get('type', 'students'),
        ]);
    }

    public function downloadTemplate(string $type)
    {
        return match ($type) {
            'students' => Excel::download(new StudentImportTemplate, 'student-import-template.xlsx'),
            'student_update' => Excel::download(new StudentUpdateTemplate, 'student-update-template.xlsx'),
            'staff' => Excel::download(new StaffImportTemplate, 'staff-import-template.xlsx'),
            default => back()->with('error', 'Invalid template type.'),
        };
    }

    public function import(Request $request)
    {
        $request->validate([
            'type' => 'required|in:students,student_update,staff',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'validate_only' => 'sometimes|boolean',
        ]);

        $type = $request->input('type');

        // Granular policy check: bulk student import requires explicit permission
        if (in_array($type, ['students', 'student_update'])) {
            $this->authorize('bulkImport', Student::class);
        }

        $schoolId = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $validateOnly = $request->boolean('validate_only');

        $importer = match ($type) {
            'students' => new StudentImport($schoolId, $academicYearId, $validateOnly),
            'student_update' => new StudentBulkUpdateImport($schoolId, $academicYearId, $validateOnly),
            'staff' => new StaffImport($schoolId, $validateOnly),
        };

        if (!$importer->validateFile($request->file('file'))) {
            return back()->with('error', $importer->getErrors()[0]['message'] ?? 'Invalid file.');
        }

        Excel::import($importer, $request->file('file'));

        if ($importer->hasErrors()) {
            $logPath = $importer->writeErrorLog($type);
            return back()->with([
                'error' => "Found {$importer->getErrorCount()} error(s). Please fix and re-upload.",
                'import_errors' => array_slice($importer->getErrors(), 0, 50),
                'error_log_path' => $logPath,
            ]);
        }

        if ($validateOnly) {
            return back()->with('success', 'Validation passed! No errors found. You can now import.');
        }

        $count = $importer->getImportedCount();
        $action = $type === 'student_update' ? 'updated' : 'imported';

        return back()->with('success', "Successfully {$action} {$count} record(s).");
    }

    public function importPhotos(Request $request)
    {
        $this->authorize('bulkImport', Student::class);

        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $schoolId = app('current_school_id');
        $matched = 0;
        $notFound = [];

        foreach ($request->file('photos') as $photo) {
            $filename = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
            $student = Student::where('school_id', $schoolId)
                ->where('admission_no', $filename)
                ->first();

            if (!$student) {
                $notFound[] = $photo->getClientOriginalName();
                continue;
            }

            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }

            $path = $photo->store('students/photos', 'public');
            $student->update(['photo' => $path]);
            $matched++;
        }

        $message = "Matched and updated {$matched} student photo(s).";
        if (!empty($notFound)) {
            $message .= ' ' . count($notFound) . ' file(s) did not match any admission number.';
        }

        return back()->with([
            'success' => $message,
            'photo_not_found' => $notFound,
        ]);
    }

    public function downloadErrors(Request $request)
    {
        $path = $request->get('path');
        if (!$path || !Storage::disk('local')->exists($path)) {
            return back()->with('error', 'Error log not found.');
        }

        return Storage::disk('local')->download($path, basename($path), ['Content-Type' => 'text/csv']);
    }
}
