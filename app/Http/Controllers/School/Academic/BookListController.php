<?php

namespace App\Http\Controllers\School\Academic;

use App\Http\Controllers\Controller;
use App\Models\BookList;
use App\Models\CourseClass;
use App\Models\ClassSubject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BookListController extends Controller
{
    public function create()
    {
        $schoolId = app('current_school_id');

        return Inertia::render('School/Academic/BookList/Create', [
            'classes' => CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = BookList::with(['courseClass', 'subject'])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId);

        if ($request->filled('class_id')) $query->where('class_id', $request->class_id);

        $books = $query->orderBy('class_id')->get();

        // Subjects scoped to the selected class via ClassSubject assignments
        $subjectsForClass = [];
        if ($request->filled('class_id')) {
            $subjectsForClass = ClassSubject::where('school_id', $schoolId)
                ->where('course_class_id', $request->class_id)
                ->with('subject')
                ->get()
                ->pluck('subject')
                ->unique('id')
                ->values();
        }

        return Inertia::render('School/Academic/BookList/Index', [
            'books'            => $books,
            'classes'          => CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get(),
            'subjectsForClass' => $subjectsForClass,
            'filters'          => $request->only(['class_id']),
        ]);
    }

    public function subjectsForClass(Request $request, $classId)
    {
        $schoolId = app('current_school_id');
        $subjects = ClassSubject::where('school_id', $schoolId)
            ->where('course_class_id', $classId)
            ->with('subject:id,name')
            ->get()
            ->pluck('subject')
            ->unique('id')
            ->values();

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $validated = $request->validate([
            'class_id'  => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'subject_id'=> ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'book_name' => 'required|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'author'    => 'nullable|string|max:255',
            'isbn'      => 'nullable|string|max:20',
        ]);

        // Prevent exact duplicates (same book in same class/subject/year)
        $exists = BookList::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->whereRaw('LOWER(book_name) = ?', [strtolower($validated['book_name'])])
            ->exists();

        if ($exists) {
            return back()->withErrors(['book_name' => 'This book is already in the list for this class and subject.']);
        }

        BookList::create(array_merge($validated, [
            'school_id'       => $schoolId,
            'academic_year_id'=> $academicYearId,
        ]));

        return back()->with('success', 'Book added to list.');
    }

    public function destroy(BookList $bookList)
    {
        if ($bookList->school_id !== app('current_school_id')) abort(403);
        $bookList->delete();
        return back()->with('success', 'Book removed from list.');
    }

    /**
     * Export book list as CSV.
     */
    public function export(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = BookList::with(['courseClass', 'subject'])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId);

        if ($request->filled('class_id')) $query->where('class_id', $request->class_id);

        $books = $query->orderBy('class_id')->get();

        $filename = 'book-list-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($books) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, ['Class', 'Subject', 'Book Name', 'Author', 'Publisher', 'ISBN']);
            foreach ($books as $b) {
                fputcsv($fp, [
                    $b->courseClass->name ?? '',
                    $b->subject->name ?? '',
                    $b->book_name,
                    $b->author ?? '',
                    $b->publisher ?? '',
                    $b->isbn ?? '',
                ]);
            }
            fclose($fp);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
