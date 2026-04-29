<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $adminUserId = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['principal', 'admin', 'school_admin'])
            ->value('id');

        Schema::disableForeignKeyConstraints();
        DB::table('library_issues')->where('school_id', $schoolId)->delete();
        DB::table('library_books')->where('school_id', $schoolId)->delete();
        DB::table('library_settings')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Library settings ───────────────────────────────────────────────
        DB::table('library_settings')->insert([
            'school_id'          => $schoolId,
            'max_issue_days'     => 14,
            'fine_per_day'       => 2.00,
            'max_books_student'  => 3,
            'max_books_staff'    => 5,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        // ── 2. Books catalog (~30 books across categories) ────────────────────
        $books = [
            ['title' => 'NCERT Mathematics Class X',           'author' => 'NCERT',                  'category' => 'Textbook',    'subject' => 'Mathematics', 'isbn' => '978-81-7450-001-1', 'publisher' => 'NCERT'],
            ['title' => 'NCERT Science Class X',               'author' => 'NCERT',                  'category' => 'Textbook',    'subject' => 'Science',     'isbn' => '978-81-7450-002-1', 'publisher' => 'NCERT'],
            ['title' => 'NCERT English Class X',               'author' => 'NCERT',                  'category' => 'Textbook',    'subject' => 'English',     'isbn' => '978-81-7450-003-1', 'publisher' => 'NCERT'],
            ['title' => 'Wings of Fire',                        'author' => 'A.P.J. Abdul Kalam',     'category' => 'Biography',   'subject' => 'Inspiration', 'isbn' => '978-81-7371-146-6', 'publisher' => 'Universities Press'],
            ['title' => 'The Discovery of India',               'author' => 'Jawaharlal Nehru',       'category' => 'History',     'subject' => 'History',     'isbn' => '978-01-4303-103-1', 'publisher' => 'Penguin'],
            ['title' => 'Panchatantra Stories',                 'author' => 'Vishnu Sharma',          'category' => 'Story',       'subject' => 'Literature',  'isbn' => '978-81-2231-001-1', 'publisher' => 'Diamond Books'],
            ['title' => 'Malgudi Days',                         'author' => 'R.K. Narayan',           'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-01-4118-526-7', 'publisher' => 'Penguin'],
            ['title' => 'Train to Pakistan',                    'author' => 'Khushwant Singh',        'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-01-4029-118-5', 'publisher' => 'Penguin'],
            ['title' => 'The God of Small Things',              'author' => 'Arundhati Roy',          'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-01-4302-857-4', 'publisher' => 'Penguin'],
            ['title' => 'The White Tiger',                      'author' => 'Aravind Adiga',          'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-00-6135-642-1', 'publisher' => 'HarperCollins'],
            ['title' => 'Harry Potter and the Sorcerer\'s Stone','author' => 'J.K. Rowling',           'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-04-3970-818-8', 'publisher' => 'Bloomsbury'],
            ['title' => 'The Hobbit',                            'author' => 'J.R.R. Tolkien',         'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-05-4792-822-7', 'publisher' => 'HarperCollins'],
            ['title' => 'Charlotte\'s Web',                      'author' => 'E.B. White',             'category' => 'Story',       'subject' => 'Literature',  'isbn' => '978-00-6440-055-8', 'publisher' => 'HarperCollins'],
            ['title' => 'A Brief History of Time',               'author' => 'Stephen Hawking',        'category' => 'Reference',   'subject' => 'Physics',     'isbn' => '978-05-5338-016-3', 'publisher' => 'Bantam'],
            ['title' => 'Sapiens: A Brief History of Humankind', 'author' => 'Yuval Noah Harari',      'category' => 'Reference',   'subject' => 'History',     'isbn' => '978-00-6231-609-7', 'publisher' => 'Harper'],
            ['title' => 'Origin of Species',                     'author' => 'Charles Darwin',         'category' => 'Reference',   'subject' => 'Biology',     'isbn' => '978-01-4043-205-2', 'publisher' => 'Penguin Classics'],
            ['title' => 'Man\'s Search for Meaning',             'author' => 'Viktor Frankl',          'category' => 'Self-help',   'subject' => 'Psychology',  'isbn' => '978-08-0701-429-5', 'publisher' => 'Beacon Press'],
            ['title' => 'Atomic Habits',                          'author' => 'James Clear',            'category' => 'Self-help',   'subject' => 'Psychology',  'isbn' => '978-07-3521-129-2', 'publisher' => 'Penguin'],
            ['title' => 'Thinking, Fast and Slow',                'author' => 'Daniel Kahneman',        'category' => 'Reference',   'subject' => 'Psychology',  'isbn' => '978-03-7427-563-1', 'publisher' => 'Farrar, Straus'],
            ['title' => 'The Great Gatsby',                       'author' => 'F. Scott Fitzgerald',    'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-07-4327-356-5', 'publisher' => 'Scribner'],
            ['title' => 'To Kill a Mockingbird',                  'author' => 'Harper Lee',             'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-00-6112-008-4', 'publisher' => 'Harper Perennial'],
            ['title' => '1984',                                   'author' => 'George Orwell',          'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-04-5152-493-5', 'publisher' => 'Signet Classics'],
            ['title' => 'Animal Farm',                            'author' => 'George Orwell',          'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-04-5152-634-2', 'publisher' => 'Signet Classics'],
            ['title' => 'Pride and Prejudice',                    'author' => 'Jane Austen',            'category' => 'Fiction',     'subject' => 'Literature',  'isbn' => '978-01-4143-951-8', 'publisher' => 'Penguin Classics'],
            ['title' => 'Oxford English Dictionary',              'author' => 'Oxford',                 'category' => 'Reference',   'subject' => 'English',     'isbn' => '978-01-9861-186-8', 'publisher' => 'Oxford University Press'],
            ['title' => 'Indian Constitution',                    'author' => 'Govt. of India',         'category' => 'Reference',   'subject' => 'Civics',      'isbn' => '978-81-2034-555-1', 'publisher' => 'Universal Law Publishing'],
            ['title' => 'CBSE Sample Question Papers Class XII',  'author' => 'CBSE',                   'category' => 'Reference',   'subject' => 'Mathematics', 'isbn' => '978-81-7450-555-1', 'publisher' => 'CBSE'],
            ['title' => 'Concise Atlas of the World',             'author' => 'National Geographic',    'category' => 'Reference',   'subject' => 'Geography',   'isbn' => '978-14-2622-052-7', 'publisher' => 'National Geographic'],
            ['title' => 'The Diary of a Young Girl',              'author' => 'Anne Frank',             'category' => 'Biography',   'subject' => 'History',     'isbn' => '978-05-5357-712-9', 'publisher' => 'Bantam'],
            ['title' => 'Gitanjali',                              'author' => 'Rabindranath Tagore',    'category' => 'Poetry',      'subject' => 'Literature',  'isbn' => '978-81-7450-998-6', 'publisher' => 'Macmillan'],
        ];

        $bookIds = [];
        foreach ($books as $i => $b) {
            $totalCopies = rand(2, 6);
            $issuedCount = rand(0, min(2, $totalCopies));
            $bookIds[] = DB::table('library_books')->insertGetId([
                'school_id'        => $schoolId,
                'title'            => $b['title'],
                'author'           => $b['author'],
                'isbn'             => $b['isbn'],
                'publisher'        => $b['publisher'],
                'publish_year'     => rand(1990, 2024),
                'category'         => $b['category'],
                'subject'          => $b['subject'],
                'language'         => 'English',
                'location'         => 'Shelf-' . chr(65 + ($i % 6)) . ($i % 10 + 1),
                'total_copies'     => $totalCopies,
                'available_copies' => $totalCopies - $issuedCount,
                'price'            => rand(150, 1500),
                'barcode'          => 'BK' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'description'      => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ── 3. Issue records (~20) ────────────────────────────────────────────
        $studentIds = DB::table('students')->where('school_id', $schoolId)->limit(60)->pluck('id')->toArray();
        $staffIds   = DB::table('staff')->where('school_id', $schoolId)->limit(20)->pluck('id')->toArray();

        if (empty($studentIds) && empty($staffIds)) {
            $this->command->info('LibrarySeeder: no students/staff to issue books to — books seeded only.');
            return;
        }

        for ($i = 0; $i < 20; $i++) {
            $bookId    = $bookIds[array_rand($bookIds)];
            $isStudent = !empty($studentIds) && (rand(0, 100) < 80 || empty($staffIds));
            $issueDate = $now->copy()->subDays(rand(1, 30));
            $dueDate   = $issueDate->copy()->addDays(14);

            $isReturned = rand(0, 100) < 60;
            $returnDate = $isReturned ? $issueDate->copy()->addDays(rand(3, 14)) : null;
            $isOverdue  = !$isReturned && $dueDate->isPast();

            DB::table('library_issues')->insert([
                'school_id'    => $schoolId,
                'book_id'      => $bookId,
                'student_id'   => $isStudent ? $studentIds[array_rand($studentIds)] : null,
                'staff_id'     => $isStudent ? null : $staffIds[array_rand($staffIds)],
                'borrower_type'=> $isStudent ? 'student' : 'staff',
                'issue_date'   => $issueDate->format('Y-m-d'),
                'due_date'     => $dueDate->format('Y-m-d'),
                'return_date'  => $returnDate?->format('Y-m-d'),
                'status'       => $isReturned ? 'returned' : ($isOverdue ? 'overdue' : 'issued'),
                'fine_amount'  => $isOverdue ? rand(10, 60) : 0,
                'fine_paid'    => false,
                'issued_by'    => $adminUserId,
                'returned_to'  => $isReturned ? $adminUserId : null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        $this->command->info('✅ Library seeded: 30 books, 20 issue records, settings.');
    }
}
