<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\Section;
use App\Models\StudentAcademicHistory;
use App\Services\ChatService;
use Illuminate\Console\Command;

class BackfillChatGroups extends Command
{
    protected $signature   = 'chat:backfill-section-groups {--school= : Limit to a specific school_id}';
    protected $description = 'Create section chat groups for all existing sections and populate members';

    public function handle(ChatService $chatService): int
    {
        $schoolId = $this->option('school');

        $query = Section::with(['courseClass', 'inchargeStaff.user']);
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $sections = $query->get();
        $this->info("Found {$sections->count()} sections to process.");

        $bar = $this->output->createProgressBar($sections->count());
        $bar->start();

        $created = 0;
        $skipped = 0;
        $membersAdded = 0;

        foreach ($sections as $section) {
            // Create/get the section group
            $conv = $chatService->ensureSectionGroup($section, $section->school_id);
            $isNew = $conv->wasRecentlyCreated;
            $isNew ? $created++ : $skipped++;

            // ── Add incharge teacher as admin ─────────────────────────────
            if ($section->inchargeStaff?->user_id) {
                $added = ChatParticipant::firstOrCreate(
                    ['conversation_id' => $conv->id, 'user_id' => $section->inchargeStaff->user_id],
                    ['role' => 'admin', 'joined_at' => now()]
                );
                if ($added->wasRecentlyCreated) $membersAdded++;
            }

            // ── Add all students currently in this section ─────────────────
            $studentUserIds = StudentAcademicHistory::where('section_id', $section->id)
                ->join('students', 'students.id', '=', 'student_academic_histories.student_id')
                ->whereNotNull('students.user_id')
                ->pluck('students.user_id')
                ->filter()
                ->unique();

            foreach ($studentUserIds as $uid) {
                $p = ChatParticipant::firstOrCreate(
                    ['conversation_id' => $conv->id, 'user_id' => $uid],
                    ['role' => 'member', 'joined_at' => now()]
                );
                if ($p->wasRecentlyCreated) $membersAdded++;
            }

            // ── Add parent users of those students ────────────────────────
            $parentUserIds = StudentAcademicHistory::where('section_id', $section->id)
                ->join('students', 'students.id', '=', 'student_academic_histories.student_id')
                ->join('parents', 'parents.id', '=', 'students.parent_id')
                ->whereNotNull('parents.user_id')
                ->pluck('parents.user_id')
                ->filter()
                ->unique();

            foreach ($parentUserIds as $uid) {
                $p = ChatParticipant::firstOrCreate(
                    ['conversation_id' => $conv->id, 'user_id' => $uid],
                    ['role' => 'member', 'joined_at' => now()]
                );
                if ($p->wasRecentlyCreated) $membersAdded++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Groups Created',  $created],
                ['Groups Existing (skipped)', $skipped],
                ['Members Added',   $membersAdded],
            ]
        );

        $this->info('✅ Section chat group backfill complete!');
        return self::SUCCESS;
    }
}
