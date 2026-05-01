<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\School;
use App\Models\ClassSubject;

class InchargeDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        if (!$school) {
            $this->command->error('No school found.');
            return;
        }
        $schoolId = $school->id;

        // Teaching staff only — query by designation name so IDs stay dynamic.
        $teachingDesigIds = DB::table('designations')
            ->where('school_id', $schoolId)
            ->whereIn('name', [
                'Head of Department', 'Academic Coordinator',
                'PGT Teacher', 'TGT Teacher', 'PRT Teacher', 'PT Teacher',
            ])
            ->pluck('id');

        $staffIds = DB::table('staff')
            ->where('school_id', $schoolId)
            ->whereIn('designation_id', $teachingDesigIds)
            ->pluck('id')
            ->shuffle()
            ->values()
            ->toArray();

        if (empty($staffIds)) {
            $this->command->error('No teaching staff found — run HRStaffSeeder first.');
            return;
        }

        $staffCount = count($staffIds);
        $idx = 0;
        $next = function () use ($staffIds, $staffCount, &$idx): int {
            return $staffIds[$idx++ % $staffCount];
        };

        // ── 1. Fill any NULL class incharges (idempotent) ─────────────────────
        $classesUpdated = DB::table('course_classes')
            ->where('school_id', $schoolId)
            ->whereNull('incharge_staff_id')
            ->get();

        foreach ($classesUpdated as $cls) {
            DB::table('course_classes')
                ->where('id', $cls->id)
                ->update(['incharge_staff_id' => $next()]);
        }

        // ── 2. Fill any NULL section incharges (idempotent) ───────────────────
        $sectionsUpdated = DB::table('sections')
            ->where('school_id', $schoolId)
            ->whereNull('incharge_staff_id')
            ->get();

        foreach ($sectionsUpdated as $sec) {
            DB::table('sections')
                ->where('id', $sec->id)
                ->update(['incharge_staff_id' => $next()]);
        }

        // ── 3. Fill any NULL class-level subject incharges (idempotent) ───────
        $csUpdated = DB::table('class_subjects')
            ->where('school_id', $schoolId)
            ->whereNull('incharge_staff_id')
            ->get();

        foreach ($csUpdated as $cs) {
            DB::table('class_subjects')
                ->where('id', $cs->id)
                ->update(['incharge_staff_id' => $next()]);
        }

        // ── 4. Section-level subject rows ─────────────────────────────────────
        // For every class-level class_subject row (both scholastic and
        // co-scholastic), create one override per section so every subject
        // — including PE, Art, Music — has its own section incharge.
        $classSubjects = DB::table('class_subjects')
            ->where('school_id', $schoolId)
            ->whereNull('section_id')
            ->get();

        $sections = DB::table('sections')
            ->where('school_id', $schoolId)
            ->get()
            ->groupBy('course_class_id');

        $created = 0;
        $skipped = 0;

        foreach ($classSubjects as $cs) {
            $classSections = $sections->get($cs->course_class_id, collect());

            foreach ($classSections as $section) {
                $exists = DB::table('class_subjects')
                    ->where('school_id', $schoolId)
                    ->where('course_class_id', $cs->course_class_id)
                    ->where('section_id', $section->id)
                    ->where('subject_id', $cs->subject_id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Use Eloquent create so ClassSubjectObserver fires and chat
                // rosters are synced automatically. Observer wraps ChatService
                // in try/catch so a chat failure won't abort the seeder.
                ClassSubject::create([
                    'school_id'        => $schoolId,
                    'course_class_id'  => $cs->course_class_id,
                    'section_id'       => $section->id,
                    'subject_id'       => $cs->subject_id,
                    'is_co_scholastic' => (bool) $cs->is_co_scholastic,
                    'incharge_staff_id'=> $next(),
                ]);

                $created++;
            }
        }

        $this->command->info('✅ Incharge Dummy Data seeded:');
        $this->command->info('   - ' . count($classesUpdated)  . ' class incharge(s) filled');
        $this->command->info('   - ' . count($sectionsUpdated) . ' section incharge(s) filled');
        $this->command->info('   - ' . count($csUpdated)       . ' class-subject incharge(s) filled');
        $this->command->info('   - ' . $created . ' section-level subject rows created (' . $skipped . ' already existed)');
    }
}
