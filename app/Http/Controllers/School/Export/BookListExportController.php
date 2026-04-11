<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\BookList;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class BookListExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = BookList::with(['courseClass', 'subject'])
            ->where('school_id', $schoolId);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $books = $query->orderBy('class_id')->get();

        $headers = ['S.No', 'Class', 'Subject', 'Book Name', 'Author', 'Publisher', 'ISBN'];

        $rows = [];
        foreach ($books as $i => $b) {
            $rows[] = [
                $i + 1,
                $b->courseClass?->name ?? '',
                $b->subject?->name ?? '',
                $b->book_name,
                $b->author ?? '',
                $b->publisher ?? '',
                $b->isbn ?? '',
            ];
        }

        return $this->exportResponse($request, $headers, $rows, 'book-list-export-' . now()->format('Y-m-d'));
    }
}
