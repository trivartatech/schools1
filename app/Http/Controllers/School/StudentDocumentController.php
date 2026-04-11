<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentDocumentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Student $student)
    {
        if (auth()->user()->isParent()) {
            abort_unless($student->parent_id === auth()->user()->studentParent?->id, 403, 'Unauthorized access.');
        } elseif (auth()->user()->isStudent()) {
            abort_unless($student->id === auth()->user()->student?->id, 403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'document_type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'is_original_submitted' => 'required|boolean',
            'original_file_location' => 'nullable|string|max:255',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('students/documents', 'public');
        }

        $student->documents()->create([
            'school_id' => app('current_school_id'),
            'document_type' => $validated['document_type'],
            'title' => $validated['title'],
            'is_original_submitted' => $validated['is_original_submitted'],
            'original_file_location' => $validated['original_file_location'],
            'file_path' => $filePath,
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student, StudentDocument $document)
    {
        if (auth()->user()->isParent()) {
            abort_unless($student->parent_id === auth()->user()->studentParent?->id, 403, 'Unauthorized access.');
        } elseif (auth()->user()->isStudent()) {
            abort_unless($student->id === auth()->user()->student?->id, 403, 'Unauthorized access.');
        }

        // Ensure the document belongs to the student
        if ($document->student_id !== $student->id) {
            abort(403);
        }

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('success', 'Document deleted successfully.');
    }
}
