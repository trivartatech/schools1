<?php

namespace App\Http\Controllers\School\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryIssue;
use App\Models\LibrarySetting;
use App\Models\Staff;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LibraryController extends Controller
{
    // ── Dashboard ────────────────────────────────────────────────────
    public function dashboard()
    {
        $schoolId = app('current_school_id');

        $totalBooks      = LibraryBook::where('school_id', $schoolId)->sum('total_copies');
        $availableBooks  = LibraryBook::where('school_id', $schoolId)->sum('available_copies');
        $activeIssues    = LibraryIssue::where('school_id', $schoolId)->where('status', 'issued')->count();
        $overdueIssues   = LibraryIssue::where('school_id', $schoolId)
            ->where('status', 'issued')
            ->where('due_date', '<', now()->toDateString())
            ->count();
        $totalFines      = LibraryIssue::where('school_id', $schoolId)
            ->where('fine_amount', '>', 0)
            ->where('fine_paid', false)
            ->sum('fine_amount');

        $recentIssues = LibraryIssue::where('school_id', $schoolId)
            ->with(['book', 'student', 'staff.user'])
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('School/Library/Dashboard', [
            'stats' => compact('totalBooks', 'availableBooks', 'activeIssues', 'overdueIssues', 'totalFines'),
            'recentIssues' => $recentIssues,
        ]);
    }

    // ── Books catalog ────────────────────────────────────────────────
    public function books(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = LibraryBook::where('school_id', $schoolId);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('author', 'like', "%{$s}%")
                  ->orWhere('isbn', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%");
            });
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $books      = $query->orderBy('title')->paginate(20)->withQueryString();
        $categories = LibraryBook::where('school_id', $schoolId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return Inertia::render('School/Library/Books', [
            'books'      => $books,
            'categories' => $categories,
            'filters'    => $request->only('search', 'category'),
        ]);
    }

    public function storeBook(Request $request)
    {
        $schoolId  = app('current_school_id');
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'author'         => 'nullable|string|max:255',
            'isbn'           => ['nullable', 'string', 'max:30', Rule::unique('library_books', 'isbn')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'publisher'      => 'nullable|string|max:255',
            'publish_year'   => 'nullable|integer|min:1800|max:' . now()->year,
            'category'       => 'nullable|string|max:100',
            'subject'        => 'nullable|string|max:100',
            'language'       => 'nullable|string|max:50',
            'location'       => 'nullable|string|max:100',
            'total_copies'   => 'required|integer|min:1',
            'price'          => 'nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'barcode'        => ['nullable', 'string', 'max:50', Rule::unique('library_books', 'barcode')->whereNull('deleted_at')],
        ]);

        $validated['school_id']        = $schoolId;
        $validated['available_copies'] = $validated['total_copies'];

        LibraryBook::create($validated);

        return back()->with('success', 'Book added to catalog.');
    }

    public function updateBook(Request $request, LibraryBook $book)
    {
        abort_if($book->school_id !== app('current_school_id'), 403);

        $schoolId  = app('current_school_id');
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'author'       => 'nullable|string|max:255',
            'isbn'         => ['nullable', 'string', 'max:30', Rule::unique('library_books', 'isbn')->where('school_id', $schoolId)->whereNull('deleted_at')->ignore($book->id)],
            'publisher'    => 'nullable|string|max:255',
            'publish_year' => 'nullable|integer|min:1800|max:' . now()->year,
            'category'     => 'nullable|string|max:100',
            'subject'      => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:50',
            'location'     => 'nullable|string|max:100',
            'total_copies' => 'required|integer|min:1',
            'price'        => 'nullable|numeric|min:0',
            'description'  => 'nullable|string',
            'barcode'      => ['nullable', 'string', 'max:50', Rule::unique('library_books', 'barcode')->whereNull('deleted_at')->ignore($book->id)],
        ]);

        // Adjust available_copies if total changed
        $delta = $validated['total_copies'] - $book->total_copies;
        $validated['available_copies'] = max(0, $book->available_copies + $delta);

        $book->update($validated);

        return back()->with('success', 'Book updated.');
    }

    public function destroyBook(LibraryBook $book)
    {
        abort_if($book->school_id !== app('current_school_id'), 403);
        abort_if($book->issues()->where('status', 'issued')->exists(), 422, 'Cannot delete a book that has active issues.');

        $book->delete();

        return back()->with('success', 'Book removed from catalog.');
    }

    // ── Issues (lending) ─────────────────────────────────────────────
    public function issues(Request $request)
    {
        $schoolId = app('current_school_id');
        $settings = LibrarySetting::firstOrCreate(
            ['school_id' => $schoolId],
            ['max_issue_days' => 14, 'fine_per_day' => 1.00, 'max_books_student' => 3, 'max_books_staff' => 5]
        );

        // Auto-mark overdue
        LibraryIssue::where('school_id', $schoolId)
            ->where('status', 'issued')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $query = LibraryIssue::where('school_id', $schoolId)
            ->with(['book', 'student', 'staff.user', 'issuedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('borrower_type')) {
            $query->where('borrower_type', $request->borrower_type);
        }

        $issues   = $query->latest()->paginate(20)->withQueryString();
        $books    = LibraryBook::where('school_id', $schoolId)->where('available_copies', '>', 0)->orderBy('title')->get(['id', 'title', 'author', 'available_copies']);
        $students = Student::where('school_id', $schoolId)->where('status', 'active')->enrolledInCurrentYear()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'admission_no']);
        $staff    = Staff::where('school_id', $schoolId)->where('status', '!=', 'inactive')->with('user:id,name')->get(['id', 'user_id', 'employee_id']);

        return Inertia::render('School/Library/Issues', [
            'issues'   => $issues,
            'books'    => $books,
            'students' => $students,
            'staff'    => $staff,
            'settings' => $settings,
            'filters'  => $request->only('status', 'borrower_type'),
        ]);
    }

    public function issueBook(Request $request)
    {
        $schoolId = app('current_school_id');
        $settings = LibrarySetting::where('school_id', $schoolId)->first()
            ?? new LibrarySetting(['max_issue_days' => 14, 'fine_per_day' => 1, 'max_books_student' => 3, 'max_books_staff' => 5]);

        $validated = $request->validate([
            'book_id'       => ['required', Rule::exists('library_books', 'id')->where('school_id', $schoolId)],
            'borrower_type' => 'required|in:student,staff',
            'student_id'    => ['required_if:borrower_type,student', 'nullable', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'staff_id'      => ['required_if:borrower_type,staff', 'nullable', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'issue_date'    => 'required|date',
            'notes'         => 'nullable|string',
        ]);

        $book = LibraryBook::where('school_id', $schoolId)->findOrFail($validated['book_id']);
        abort_if($book->available_copies < 1, 422, 'No copies available for this book.');

        // Enforce max books limit
        $currentCount = LibraryIssue::where('school_id', $schoolId)
            ->where('status', 'issued')
            ->when($validated['borrower_type'] === 'student', fn($q) => $q->where('student_id', $validated['student_id']))
            ->when($validated['borrower_type'] === 'staff',   fn($q) => $q->where('staff_id', $validated['staff_id']))
            ->count();

        $limit = $validated['borrower_type'] === 'student' ? $settings->max_books_student : $settings->max_books_staff;
        abort_if($currentCount >= $limit, 422, "This borrower already has {$currentCount} book(s) issued (limit: {$limit}).");

        DB::transaction(function () use ($validated, $book, $schoolId, $settings) {
            LibraryIssue::create([
                'school_id'     => $schoolId,
                'book_id'       => $book->id,
                'student_id'    => $validated['student_id'] ?? null,
                'staff_id'      => $validated['staff_id'] ?? null,
                'borrower_type' => $validated['borrower_type'],
                'issue_date'    => $validated['issue_date'],
                'due_date'      => Carbon::parse($validated['issue_date'])->addDays($settings->max_issue_days)->toDateString(),
                'status'        => 'issued',
                'issued_by'     => auth()->id(),
                'notes'         => $validated['notes'] ?? null,
            ]);

            $book->decrement('available_copies');
        });

        return back()->with('success', "Book issued. Due: " . Carbon::parse($validated['issue_date'])->addDays($settings->max_issue_days)->format('d M Y') . ".");
    }

    public function returnBook(Request $request, LibraryIssue $issue)
    {
        abort_if($issue->school_id !== app('current_school_id'), 403);
        abort_if(in_array($issue->status, ['returned', 'lost']), 422, 'This issue is already closed.');

        $validated = $request->validate([
            'return_date' => 'required|date|after_or_equal:' . $issue->issue_date,
            'notes'       => 'nullable|string',
        ]);

        $settings  = LibrarySetting::where('school_id', $issue->school_id)->first();
        $ratePerDay = $settings?->fine_per_day ?? 1.00;
        $returnDate = Carbon::parse($validated['return_date']);
        $fine = $returnDate->gt($issue->due_date)
            ? round($returnDate->diffInDays($issue->due_date) * $ratePerDay, 2)
            : 0;

        DB::transaction(function () use ($issue, $validated, $fine) {
            $issue->update([
                'return_date' => $validated['return_date'],
                'status'      => 'returned',
                'fine_amount' => $fine,
                'returned_to' => auth()->id(),
                'notes'       => $validated['notes'] ?? $issue->notes,
            ]);

            $issue->book->increment('available_copies');
        });

        $msg = "Book returned." . ($fine > 0 ? " Fine: ₹{$fine}." : "");
        return back()->with('success', $msg);
    }

    public function markFinePaid(LibraryIssue $issue)
    {
        abort_if($issue->school_id !== app('current_school_id'), 403);
        $issue->update(['fine_paid' => true]);
        return back()->with('success', 'Fine marked as paid.');
    }

    // ── Settings ─────────────────────────────────────────────────────
    public function settings()
    {
        $schoolId = app('current_school_id');
        $settings = LibrarySetting::firstOrCreate(
            ['school_id' => $schoolId],
            ['max_issue_days' => 14, 'fine_per_day' => 1.00, 'max_books_student' => 3, 'max_books_staff' => 5]
        );
        return Inertia::render('School/Library/Settings', ['settings' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        $schoolId  = app('current_school_id');
        $validated = $request->validate([
            'max_issue_days'    => 'required|integer|min:1|max:365',
            'fine_per_day'      => 'required|numeric|min:0',
            'max_books_student' => 'required|integer|min:1|max:50',
            'max_books_staff'   => 'required|integer|min:1|max:50',
        ]);

        LibrarySetting::updateOrCreate(['school_id' => $schoolId], $validated);
        return back()->with('success', 'Library settings updated.');
    }
}
