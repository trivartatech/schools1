<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\Department;
use App\Models\FeeGroup;
use App\Models\FeeHead;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentParent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrateLegacyStudentsCommand extends Command
{
    protected $signature = 'students:migrate-legacy
        {--school-slug= : Target school slug (defaults to SCHOOL_SLUG from .env)}
        {--data-dir= : Directory containing students_clean.csv / enrollments_clean.csv (default: storage/app/migrations)}
        {--dry-run : Roll back the transaction at the end — print planned counts only}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Import legacy ERP student data (2022-23 → 2025-26) into the configured school';

    private array $ayMap       = [];
    private array $classMap    = [];
    private array $sectionMap  = [];
    private array $feeHeadMap  = [];

    private array $classNumericValue = [
        'JKG' => 0, 'LKG' => 0, 'UKG' => 0,
        '1 STD' => 1, '2 ND' => 2, '3 RD' => 3, '4 TH' => 4, '5 TH' => 5,
        '6 TH' => 6, '7 TH' => 7, '8 TH' => 8, '9 TH' => 9, '10 TH' => 10,
        '10 TH STATE' => 10,
    ];

    private array $feeHeadDefs = [
        ['name' => 'Tuition Fee',    'short_code' => 'TF',  'sort_order' => 1],
        ['name' => 'Admission Fee',  'short_code' => 'AF',  'sort_order' => 2],
        ['name' => 'Stationery Fee', 'short_code' => 'SF',  'sort_order' => 3],
        ['name' => 'Hostel Fee',     'short_code' => 'HF',  'sort_order' => 4],
        ['name' => 'Transport Fee',  'short_code' => 'TRF', 'sort_order' => 5],
        ['name' => 'Other Fee',      'short_code' => 'OF',  'sort_order' => 6],
        ['name' => 'Extra Fee',      'short_code' => 'EF',  'sort_order' => 7],
    ];

    private array $academicYearDefs = [
        ['name' => '2022-23', 'start' => '2022-04-01', 'end' => '2023-03-31', 'is_current' => false, 'status' => 'frozen'],
        ['name' => '2023-24', 'start' => '2023-04-01', 'end' => '2024-03-31', 'is_current' => false, 'status' => 'frozen'],
        ['name' => '2024-25', 'start' => '2024-04-01', 'end' => '2025-03-31', 'is_current' => false, 'status' => 'frozen'],
        ['name' => '2025-26', 'start' => '2025-04-01', 'end' => '2026-03-31', 'is_current' => false, 'status' => 'frozen'],
        ['name' => '2026-27', 'start' => '2026-04-01', 'end' => '2027-03-31', 'is_current' => true,  'status' => 'active'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $dataDir = $this->option('data-dir') ?: storage_path('app/migrations');
        $studentsCsv     = $dataDir . '/students_clean.csv';
        $enrollmentsCsv  = $dataDir . '/enrollments_clean.csv';

        $slug = $this->option('school-slug') ?: config('school.slug') ?: env('SCHOOL_SLUG');
        if (!$slug) {
            $this->error('No school slug provided. Pass --school-slug=… or set SCHOOL_SLUG in .env.');
            return self::FAILURE;
        }
        $school = School::where('slug', $slug)->first();
        if (!$school) {
            $this->error("School with slug '{$slug}' not found. Run GenericSchoolSeeder first (set SCHOOL_SLUG in .env).");
            return self::FAILURE;
        }
        if (!is_file($studentsCsv) || !is_file($enrollmentsCsv)) {
            $this->error("CSVs missing in {$dataDir}. Run scripts/migrate_legacy_students.py first.");
            return self::FAILURE;
        }

        $schoolId = $school->id;
        $this->info("Target school: {$school->name} (id={$schoolId})");
        $this->info("Dry run: " . ($dryRun ? 'YES (will roll back)' : 'NO (will commit)'));

        $existingCounts = $this->existingCounts($schoolId);
        $this->line(sprintf(
            "About to wipe — students: %d, parents: %d, fee_payments: %d, histories: %d, sections: %d, classes: %d",
            $existingCounts['students'],
            $existingCounts['parents'],
            $existingCounts['fee_payments'],
            $existingCounts['histories'],
            $existingCounts['sections'],
            $existingCounts['classes']
        ));

        if (!$force && !$dryRun && !$this->confirm('Proceed? This will DESTROY existing data for this school.')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $logPath = storage_path('logs/migrate-legacy-' . now()->format('Ymd-His') . '.log');
        $logger  = Log::build([
            'driver' => 'single',
            'path'   => $logPath,
            'level'  => 'info',
        ]);
        $logger->info("=== Legacy migration start (school_id={$schoolId}, dry_run=" . ($dryRun ? '1' : '0') . ') ===');

        try {
            DB::beginTransaction();

            $this->line('');
            $this->line('[1/6] Wiping placeholder data…');
            $wiped = $this->wipeSchoolData($schoolId);
            $logger->info('Wipe counts', $wiped);

            $this->line('[2/6] Ensuring academic years…');
            $this->ensureAcademicYears($schoolId);

            $this->line('[3/6] Ensuring taxonomy (department/classes/sections/fee heads)…');
            $this->ensureTaxonomy($schoolId, $enrollmentsCsv);

            $this->line('[4/6] Importing students + parents…');
            $studentStats = $this->importStudents($schoolId, $studentsCsv);
            $logger->info('Student import', $studentStats);

            $this->line('[5/6] Importing enrollments + fee payments…');
            $enrollStats = $this->importEnrollments($schoolId, $enrollmentsCsv);
            $logger->info('Enrollment import', $enrollStats);

            $this->line('[6/6] Summary');
            $this->printSummary($schoolId, $studentStats, $enrollStats);

            if ($dryRun) {
                DB::rollBack();
                $this->warn('DRY RUN — transaction rolled back. Nothing persisted.');
                $logger->info('=== DRY RUN rolled back ===');
            } else {
                DB::commit();
                $this->info('Migration committed.');
                $logger->info('=== Migration committed ===');
            }

            $this->line("Log: {$logPath}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Migration failed: ' . $e->getMessage());
            $this->error($e->getFile() . ':' . $e->getLine());
            $logger->error('FAILURE: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return self::FAILURE;
        }
    }

    private function existingCounts(int $schoolId): array
    {
        return [
            'students'     => DB::table('students')->where('school_id', $schoolId)->count(),
            'parents'      => DB::table('parents')->where('school_id', $schoolId)->count(),
            'fee_payments' => DB::table('fee_payments')->where('school_id', $schoolId)->count(),
            'histories'    => DB::table('student_academic_histories')->where('school_id', $schoolId)->count(),
            'sections'     => DB::table('sections')->where('school_id', $schoolId)->count(),
            'classes'      => DB::table('course_classes')->where('school_id', $schoolId)->count(),
        ];
    }

    private function wipeSchoolData(int $schoolId): array
    {
        $counts = [];

        $counts['fee_payments'] = DB::table('fee_payments')->where('school_id', $schoolId)->delete();

        if (DB::getSchemaBuilder()->hasTable('fee_structure_fee_head')) {
            DB::table('fee_structure_fee_head')
                ->whereIn('fee_structure_id', function ($q) use ($schoolId) {
                    $q->select('id')->from('fee_structures')->where('school_id', $schoolId);
                })
                ->delete();
        }
        $counts['fee_structures'] = DB::table('fee_structures')->where('school_id', $schoolId)->delete();

        $counts['histories'] = DB::table('student_academic_histories')->where('school_id', $schoolId)->delete();

        $studentIds = DB::table('students')->where('school_id', $schoolId)->pluck('id');
        if ($studentIds->isNotEmpty()) {
            if (DB::getSchemaBuilder()->hasTable('student_documents')) {
                DB::table('student_documents')->whereIn('student_id', $studentIds)->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('student_health_records')) {
                DB::table('student_health_records')->whereIn('student_id', $studentIds)->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('transport_student_allocations')) {
                DB::table('transport_student_allocations')->whereIn('student_id', $studentIds)->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('hostel_students')) {
                DB::table('hostel_students')->whereIn('student_id', $studentIds)->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('house_students')) {
                DB::table('house_students')->whereIn('student_id', $studentIds)->delete();
            }
        }

        $counts['students'] = DB::table('students')->where('school_id', $schoolId)->delete();
        $counts['parents']  = DB::table('parents')->where('school_id', $schoolId)->delete();

        $counts['sections']   = DB::table('sections')->where('school_id', $schoolId)->delete();
        $counts['classes']    = DB::table('course_classes')->where('school_id', $schoolId)->delete();

        $counts['fee_heads']  = DB::table('fee_heads')->where('school_id', $schoolId)->delete();
        $counts['fee_groups'] = DB::table('fee_groups')->where('school_id', $schoolId)->delete();

        return $counts;
    }

    private function ensureAcademicYears(int $schoolId): void
    {
        // Reset current flag on anything pre-existing.
        DB::table('academic_years')->where('school_id', $schoolId)
            ->update(['is_current' => false, 'updated_at' => now()]);

        // Legacy seeders used "YYYY-YYYY" format; canonical format here is "YY-YY"
        // matching the Student model's erp_no prefix convention.
        $renames = [
            '2022-2023' => '2022-23', '2023-2024' => '2023-24',
            '2024-2025' => '2024-25', '2025-2026' => '2025-26',
            '2026-2027' => '2026-27',
        ];
        foreach ($renames as $long => $short) {
            DB::table('academic_years')
                ->where('school_id', $schoolId)
                ->where('name', $long)
                ->update(['name' => $short, 'updated_at' => now()]);
        }

        foreach ($this->academicYearDefs as $def) {
            $ay = AcademicYear::firstOrNew([
                'school_id' => $schoolId,
                'name'      => $def['name'],
            ]);
            $ay->start_date = $def['start'];
            $ay->end_date   = $def['end'];
            $ay->is_current = $def['is_current'];
            $ay->status     = $def['status'];
            if ($ay->trashed()) {
                $ay->restore();
            }
            $ay->save();
            $this->ayMap[$def['name']] = $ay->id;
        }
    }

    private function ensureTaxonomy(int $schoolId, string $enrollmentsCsv): void
    {
        $dept = Department::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'School'],
            ['type' => 'academic']
        );
        if (method_exists($dept, 'trashed') && $dept->trashed()) {
            $dept->restore();
        }

        // Build class/section inventory from enrollments CSV
        $classSections = [];
        $fp = fopen($enrollmentsCsv, 'r');
        $headers = fgetcsv($fp);
        while (($row = fgetcsv($fp)) !== false) {
            $rec       = array_combine($headers, $row);
            $className = trim($rec['class_name'] ?? '');
            $section   = trim($rec['section_name'] ?? '');
            if ($className === '') continue;
            $classSections[$className] ??= [];
            if ($section !== '') {
                $classSections[$className][$section] = true;
            }
        }
        fclose($fp);

        foreach ($classSections as $className => $sections) {
            $class = CourseClass::firstOrNew([
                'school_id' => $schoolId,
                'name'      => $className,
            ]);
            $class->department_id  = $dept->id;
            $class->numeric_value  = $this->classNumericValue[$className] ?? null;
            if (method_exists($class, 'trashed') && $class->trashed()) {
                $class->restore();
            }
            $class->save();
            $this->classMap[$className] = $class->id;

            foreach (array_keys($sections) as $sectionName) {
                $section = Section::firstOrNew([
                    'school_id'       => $schoolId,
                    'course_class_id' => $class->id,
                    'name'            => $sectionName,
                ]);
                if (method_exists($section, 'trashed') && $section->trashed()) {
                    $section->restore();
                }
                $section->save();
                $this->sectionMap[$className . '|' . $sectionName] = $section->id;
            }
        }

        $feeGroup = FeeGroup::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Annual Fees'],
            ['description' => 'Legacy annual fee heads imported from previous ERP']
        );
        if (method_exists($feeGroup, 'trashed') && $feeGroup->trashed()) {
            $feeGroup->restore();
        }

        foreach ($this->feeHeadDefs as $def) {
            $head = FeeHead::firstOrNew([
                'school_id' => $schoolId,
                'name'      => $def['name'],
            ]);
            $head->fee_group_id  = $feeGroup->id;
            $head->short_code    = $def['short_code'];
            $head->sort_order    = $def['sort_order'];
            if (method_exists($head, 'trashed') && $head->trashed()) {
                $head->restore();
            }
            $head->save();
            $this->feeHeadMap[$def['name']] = $head->id;
        }
    }

    private function importStudents(int $schoolId, string $studentsCsv): array
    {
        $count = count(file($studentsCsv)) - 1;
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat('  students: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
        $bar->start();

        $fp      = fopen($studentsCsv, 'r');
        $headers = fgetcsv($fp);

        // Pre-computed ERP number sequence per year (matches Student::generateErpNo format)
        $erpSeq  = ['2022-23' => 0, '2023-24' => 0, '2024-25' => 0, '2025-26' => 0];
        $phoneToParentId = [];
        $studentIdByAdmission = [];

        $studentsCreated = 0;
        $parentsCreated  = 0;

        while (($row = fgetcsv($fp)) !== false) {
            $rec = array_combine($headers, $row);

            // Resolve or create parent (dedup by father_phone + primary_phone)
            $fatherPhone = trim($rec['father_phone'] ?? '');
            $motherPhone = trim($rec['mother_phone'] ?? '');
            $primaryPhone = $fatherPhone !== '' ? $fatherPhone
                : ($motherPhone !== '' ? $motherPhone : 'NA-' . $rec['student_seq']);

            if (!isset($phoneToParentId[$primaryPhone])) {
                $parent = StudentParent::create([
                    'school_id'          => $schoolId,
                    'father_name'        => $this->nullIfBlank($rec['father_name']),
                    'mother_name'        => $this->nullIfBlank($rec['mother_name']),
                    'guardian_name'      => $this->nullIfBlank($rec['guardian_name']),
                    'father_phone'       => $this->nullIfBlank($fatherPhone),
                    'mother_phone'       => $this->nullIfBlank($motherPhone),
                    'primary_phone'      => $primaryPhone,
                    'guardian_phone'     => $this->nullIfBlank($rec['guardian_phone']),
                    'father_occupation'  => $this->nullIfBlank($rec['father_occupation'] ?? null),
                    'mother_occupation'  => $this->nullIfBlank($rec['mother_occupation'] ?? null),
                    'address'            => $this->nullIfBlank($rec['address']),
                ]);
                $phoneToParentId[$primaryPhone] = $parent->id;
                $parentsCreated++;
            }
            $parentId = $phoneToParentId[$primaryPhone];

            $firstYear = $rec['first_year'];
            $erpSeq[$firstYear] = ($erpSeq[$firstYear] ?? 0) + 1;
            $erpNo = 'ERP_' . $firstYear . '_' . str_pad($erpSeq[$firstYear], 4, '0', STR_PAD_LEFT);

            $ayId = $this->ayMap[$firstYear] ?? null;
            if ($ayId) {
                app()->instance('current_academic_year_id', $ayId);
            }

            $student = new Student();
            $student->school_id     = $schoolId;
            $student->parent_id     = $parentId;
            $student->admission_no  = $rec['admission_no'];
            $student->erp_no        = $erpNo;
            $student->first_name    = $this->nullIfBlank($rec['first_name']) ?? $rec['full_name_raw'];
            $student->last_name     = $this->nullIfBlank($rec['last_name']);
            $student->dob           = $this->parseDob($rec['dob'] ?? null);
            $student->gender        = $this->normalizeGender($rec['gender'] ?? null);
            $student->blood_group   = $this->nullIfBlank($rec['blood_group']);
            $student->religion      = $this->nullIfBlank($rec['religion']);
            $student->category      = $this->nullIfBlank($rec['category']);
            $student->caste         = $this->nullIfBlank($rec['cast']);
            $student->aadhaar_no    = $this->nullIfBlank($rec['aadhaar_no']);
            $student->mother_tongue = $this->nullIfBlank($rec['mother_tongue']);
            $student->birth_place   = $this->nullIfBlank($rec['birth_place']);
            $student->address       = $this->nullIfBlank($rec['address']);
            $student->city          = $this->nullIfBlank($rec['village'] ?? null) ?: $this->nullIfBlank($rec['taluk'] ?? null);
            $student->state         = $this->nullIfBlank($rec['state'] ?? null);
            $student->status        = 'active';
            $student->save();

            $studentIdByAdmission[$rec['admission_no']] = $student->id;
            $studentsCreated++;

            $bar->advance();
        }
        fclose($fp);
        app()->forgetInstance('current_academic_year_id');

        $bar->finish();
        $this->newLine();

        // Stash for enrollment pass
        $this->studentIdByAdmission = $studentIdByAdmission;

        return [
            'students_created' => $studentsCreated,
            'parents_created'  => $parentsCreated,
        ];
    }

    private array $studentIdByAdmission = [];

    private function importEnrollments(int $schoolId, string $enrollmentsCsv): array
    {
        $count = count(file($enrollmentsCsv)) - 1;
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat('  enrollments: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
        $bar->start();

        $fp      = fopen($enrollmentsCsv, 'r');
        $headers = fgetcsv($fp);

        $historiesBuffer = [];
        $paymentsBuffer  = [];
        $historiesSeen   = [];
        $historiesCreated = 0;
        $paymentsCreated  = 0;
        $paymentSeq       = 0;

        $feeTotals = []; // per year/head due/paid counters

        while (($row = fgetcsv($fp)) !== false) {
            $rec = array_combine($headers, $row);

            $admissionNo = $rec['admission_no'];
            $year        = $rec['year'];
            $studentId   = $this->studentIdByAdmission[$admissionNo] ?? null;
            $ayId        = $this->ayMap[$year] ?? null;
            $classId     = $this->classMap[trim($rec['class_name'])] ?? null;

            if (!$studentId || !$ayId || !$classId) {
                $bar->advance();
                continue;
            }

            $sectionName = trim($rec['section_name'] ?? '');
            $sectionId   = $sectionName !== ''
                ? ($this->sectionMap[trim($rec['class_name']) . '|' . $sectionName] ?? null)
                : null;

            // Dedup guard: (student_id, academic_year_id) is unique
            $key = $studentId . '|' . $ayId;
            if (isset($historiesSeen[$key])) {
                $bar->advance();
                continue;
            }
            $historiesSeen[$key] = true;

            $isCurrentYear = ($year === '2025-26'); // freeze-year boundary
            $newAdmission  = (int) ($rec['new_admission'] ?? 0) === 1;

            $historiesBuffer[] = [
                'school_id'        => $schoolId,
                'student_id'       => $studentId,
                'academic_year_id' => $ayId,
                'class_id'         => $classId,
                'section_id'       => $sectionId,
                'status'           => 'promoted',
                'enrollment_type'  => 'Regular',
                'student_type'     => $newAdmission ? 'New Student' : 'Old Student',
                'remarks'          => 'Migrated from legacy ERP (' . ($rec['src_file'] ?? '') . ')',
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
            $historiesCreated++;

            $paymentDate = $this->academicYearDefs[array_search(
                $year,
                array_column($this->academicYearDefs, 'name'),
                true
            )]['start'] ?? '2022-04-01';

            $srcFile = $rec['src_file'] ?? '';
            $remarks = "Migrated from legacy ERP ({$srcFile})";

            // Collect non-extra head dues for pro-rata allocation
            $nonExtraDue = 0.0;
            $heads = [
                'Admission Fee'  => (float) ($rec['admission_fee']  ?? 0),
                'Stationery Fee' => (float) ($rec['stationery_fee'] ?? 0),
                'Hostel Fee'     => (float) ($rec['hostel_fee']     ?? 0),
                'Transport Fee'  => (float) ($rec['route_fee']      ?? 0),
                'Other Fee'      => (float) ($rec['other_fee']      ?? 0),
                'Tuition Fee'    => (float) ($rec['total_fee']      ?? 0),
            ];
            foreach ($heads as $amt) {
                $nonExtraDue += max(0, $amt);
            }

            $totalPaid    = (float) ($rec['total_paid'] ?? 0);
            $totalDiscount = (float) ($rec['disc_amount'] ?? 0);
            $discApplied   = false;

            foreach ($heads as $headName => $due) {
                if ($due <= 0) continue;
                $paid = $nonExtraDue > 0 ? round($totalPaid * ($due / $nonExtraDue), 2) : 0;

                $discount = 0;
                if (!$discApplied && $headName === 'Tuition Fee' && $totalDiscount > 0) {
                    $discount    = $totalDiscount;
                    $discApplied = true;
                }

                $balance = max(0, $due - $discount - $paid);
                $status  = $balance == 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due');

                $paymentSeq++;
                $paymentsBuffer[] = $this->paymentRow([
                    'receipt_no'        => 'MIG-' . str_replace('-', '', $year) . '-' . str_pad($paymentSeq, 6, '0', STR_PAD_LEFT),
                    'school_id'         => $schoolId,
                    'student_id'        => $studentId,
                    'academic_year_id'  => $ayId,
                    'fee_head_id'       => $this->feeHeadMap[$headName],
                    'amount_due'        => $due,
                    'amount_paid'       => $paid,
                    'discount'          => $discount,
                    'balance'           => $balance,
                    'payment_date'      => $paymentDate,
                    'status'            => $status,
                    'remarks'           => $remarks,
                    'is_carry_forward'  => false,
                ]);
                $paymentsCreated++;

                $feeTotals[$year][$headName]['due']  = ($feeTotals[$year][$headName]['due']  ?? 0) + $due;
                $feeTotals[$year][$headName]['paid'] = ($feeTotals[$year][$headName]['paid'] ?? 0) + $paid;
            }

            // Extra Fee — explicit paid amount
            $extraDue  = (float) ($rec['extra_fee']  ?? 0);
            $extraPaid = (float) ($rec['extra_paid'] ?? 0);
            if ($extraDue > 0) {
                $balance = max(0, $extraDue - $extraPaid);
                $status  = $balance == 0 ? 'paid' : ($extraPaid > 0 ? 'partial' : 'due');
                $paymentSeq++;
                $paymentsBuffer[] = $this->paymentRow([
                    'receipt_no'       => 'MIG-' . str_replace('-', '', $year) . '-' . str_pad($paymentSeq, 6, '0', STR_PAD_LEFT),
                    'school_id'        => $schoolId,
                    'student_id'       => $studentId,
                    'academic_year_id' => $ayId,
                    'fee_head_id'      => $this->feeHeadMap['Extra Fee'],
                    'amount_due'       => $extraDue,
                    'amount_paid'      => $extraPaid,
                    'balance'          => $balance,
                    'payment_date'     => $paymentDate,
                    'status'           => $status,
                    'remarks'          => $remarks,
                    'is_carry_forward' => false,
                ]);
                $paymentsCreated++;
                $feeTotals[$year]['Extra Fee']['due']  = ($feeTotals[$year]['Extra Fee']['due']  ?? 0) + $extraDue;
                $feeTotals[$year]['Extra Fee']['paid'] = ($feeTotals[$year]['Extra Fee']['paid'] ?? 0) + $extraPaid;
            }

            // Old Balance — carry-forward debt under Tuition
            $oldBalance = (float) ($rec['old_balance'] ?? 0);
            if ($oldBalance > 0) {
                $prevYearIdx = array_search($year, array_column($this->academicYearDefs, 'name'), true);
                $prevYearName = $prevYearIdx !== false && $prevYearIdx > 0
                    ? $this->academicYearDefs[$prevYearIdx - 1]['name']
                    : null;
                $sourceYearId = $prevYearName ? ($this->ayMap[$prevYearName] ?? null) : null;

                $paymentSeq++;
                $paymentsBuffer[] = $this->paymentRow([
                    'receipt_no'       => 'MIG-CF-' . str_replace('-', '', $year) . '-' . str_pad($paymentSeq, 6, '0', STR_PAD_LEFT),
                    'school_id'        => $schoolId,
                    'student_id'       => $studentId,
                    'academic_year_id' => $ayId,
                    'fee_head_id'      => $this->feeHeadMap['Tuition Fee'],
                    'amount_due'       => $oldBalance,
                    'amount_paid'      => 0,
                    'balance'          => $oldBalance,
                    'payment_date'     => $paymentDate,
                    'status'           => 'due',
                    'remarks'          => $remarks . ' [carry-forward of prior year dues]',
                    'is_carry_forward' => true,
                    'source_year_id'   => $sourceYearId,
                ]);
                $paymentsCreated++;
            }

            // Flush buffers every 500 rows to avoid giant single insert
            if (count($historiesBuffer) >= 500) {
                $this->flushBatch('student_academic_histories', $historiesBuffer);
                $historiesBuffer = [];
            }
            if (count($paymentsBuffer) >= 1000) {
                $this->flushBatch('fee_payments', $paymentsBuffer);
                $paymentsBuffer = [];
            }

            $bar->advance();
        }
        fclose($fp);

        if (!empty($historiesBuffer)) {
            $this->flushBatch('student_academic_histories', $historiesBuffer);
        }
        if (!empty($paymentsBuffer)) {
            $this->flushBatch('fee_payments', $paymentsBuffer);
        }

        $bar->finish();
        $this->newLine();

        return [
            'histories_created' => $historiesCreated,
            'payments_created'  => $paymentsCreated,
            'fee_totals'        => $feeTotals,
        ];
    }

    private function printSummary(int $schoolId, array $studentStats, array $enrollStats): void
    {
        $this->line('');
        $this->line('╔════════════════════════════════════════════════════════════════╗');
        $this->line('║                  MIGRATION SUMMARY                             ║');
        $this->line('╚════════════════════════════════════════════════════════════════╝');
        $this->line(sprintf('  Students created  : %d', $studentStats['students_created']));
        $this->line(sprintf('  Parents created   : %d (deduped by primary_phone)', $studentStats['parents_created']));
        $this->line(sprintf('  Histories created : %d', $enrollStats['histories_created']));
        $this->line(sprintf('  Payments created  : %d', $enrollStats['payments_created']));
        $this->line('');
        $this->line('  Per-year fee totals (DB inserted):');

        foreach ($enrollStats['fee_totals'] ?? [] as $year => $heads) {
            $totalDue  = array_sum(array_column($heads, 'due'));
            $totalPaid = array_sum(array_column($heads, 'paid'));
            $this->line(sprintf(
                '    %s  due=%s  paid=%s  balance=%s',
                $year,
                number_format($totalDue, 2),
                number_format($totalPaid, 2),
                number_format($totalDue - $totalPaid, 2)
            ));
        }

        $this->line('');
        $this->line('  DB row counts (live):');
        $this->line(sprintf('    students                  : %d', DB::table('students')->where('school_id', $schoolId)->count()));
        $this->line(sprintf('    parents                   : %d', DB::table('parents')->where('school_id', $schoolId)->count()));
        $this->line(sprintf('    student_academic_histories: %d', DB::table('student_academic_histories')->where('school_id', $schoolId)->count()));
        $this->line(sprintf('    fee_payments              : %d', DB::table('fee_payments')->where('school_id', $schoolId)->count()));
        $this->line(sprintf('    course_classes            : %d', DB::table('course_classes')->where('school_id', $schoolId)->count()));
        $this->line(sprintf('    sections                  : %d', DB::table('sections')->where('school_id', $schoolId)->count()));
        $this->line(sprintf('    fee_heads                 : %d', DB::table('fee_heads')->where('school_id', $schoolId)->count()));
        $this->line('');
    }

    /**
     * Normalize a fee_payments row so every buffered row has the same 22 keys.
     * Prevents Laravel's DB::insert() batch from hitting "column count doesn't
     * match value count" when CF and non-CF rows mix in the same flush.
     */
    private function paymentRow(array $row): array
    {
        $defaults = [
            'receipt_no'        => null,
            'school_id'         => null,
            'student_id'        => null,
            'academic_year_id'  => null,
            'fee_head_id'       => null,
            'fee_structure_id'  => null,
            'amount_due'        => 0,
            'amount_paid'       => 0,
            'discount'          => 0,
            'fine'              => 0,
            'balance'           => 0,
            'term'              => 'annual',
            'payment_date'      => null,
            'payment_mode'      => 'cash',
            'status'            => 'paid',
            'remarks'           => null,
            'is_carry_forward'  => false,
            'source_payment_id' => null,
            'source_year_id'    => null,
            'rollover_run_id'   => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
        return array_merge($defaults, $row);
    }

    /**
     * Flush a buffered insert batch with context on failure.
     * On failure logs the table, batch size, and the keys of the first/last rows
     * so we can diagnose column-count mismatches, FK violations, etc.
     */
    private function flushBatch(string $table, array $rows): void
    {
        try {
            DB::table($table)->insert($rows);
        } catch (\Throwable $e) {
            $first = $rows[0] ?? [];
            $last  = $rows[array_key_last($rows)] ?? [];
            $this->error("Batch insert failed on '{$table}' (size=" . count($rows) . ')');
            $this->error('  message: ' . $e->getMessage());
            $this->error('  first row keys (' . count($first) . '): ' . implode(', ', array_keys($first)));
            $this->error('  last  row keys (' . count($last)  . '): ' . implode(', ', array_keys($last)));
            if (isset($first['receipt_no']))   $this->error('  first receipt_no: ' . $first['receipt_no']);
            if (isset($last['receipt_no']))    $this->error('  last  receipt_no: ' . $last['receipt_no']);
            if (isset($first['student_id']))   $this->error('  first student_id: ' . $first['student_id']);
            if (isset($last['student_id']))    $this->error('  last  student_id: ' . $last['student_id']);
            throw $e;
        }
    }

    private function nullIfBlank($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function parseDob($value): ?string
    {
        $v = $this->nullIfBlank($value);
        if ($v === null) return null;
        try {
            $d = Carbon::parse($v);
            if ($d->year < 1900 || $d->year > (int) date('Y') + 1) {
                return null;
            }
            return $d->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeGender(?string $g): ?string
    {
        if ($g === null) return null;
        $g = strtolower(trim($g));
        return match (true) {
            str_starts_with($g, 'f') => 'female',
            str_starts_with($g, 'm') => 'male',
            $g === ''                => null,
            default                  => 'other',
        };
    }
}
