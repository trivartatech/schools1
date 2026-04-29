<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        Schema::disableForeignKeyConstraints();
        DB::table('chat_message_reads')->whereIn('message_id', DB::table('chat_messages')->pluck('id'))->delete();
        DB::table('chat_messages')->whereIn('conversation_id', DB::table('chat_conversations')->where('school_id', $schoolId)->pluck('id'))->delete();
        DB::table('chat_participants')->whereIn('conversation_id', DB::table('chat_conversations')->where('school_id', $schoolId)->pluck('id'))->delete();
        DB::table('chat_typing_indicators')->whereIn('conversation_id', DB::table('chat_conversations')->where('school_id', $schoolId)->pluck('id'))->delete();
        DB::table('chat_conversations')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        $users      = DB::table('users')->where('school_id', $schoolId)->get();
        $adminUser  = $users->where('user_type', 'principal')->first() ?? $users->first();
        $teachers   = $users->whereIn('user_type', ['teacher', 'principal'])->values();
        $sections   = DB::table('sections')->where('school_id', $schoolId)->get();

        if ($users->count() < 2) return;

        $convCount = 0;
        $msgCount  = 0;

        // ── 1. Section Group Chats (one per section) ───────────────────────────
        foreach ($sections->take(5) as $section) {
            $class = DB::table('course_classes')->find($section->course_class_id);
            $convId = DB::table('chat_conversations')->insertGetId([
                'school_id'         => $schoolId,
                'type'              => 'group',
                'name'              => ($class->name ?? 'Class') . ' - Section ' . $section->name,
                'group_type'        => 'section',
                'section_id'        => $section->id,
                'is_system_managed' => true,
                'is_pinned'         => false,
                'description'       => 'Official group for ' . ($class->name ?? '') . ' Section ' . $section->name,
                'created_by'        => $adminUser->id,
                'created_at'        => $now, 'updated_at' => $now,
            ]);
            $convCount++;

            // Add teacher + admin as participants
            $participants = [$adminUser->id];
            if ($teachers->isNotEmpty()) {
                $teacher = $teachers->get($section->id % $teachers->count());
                if ($teacher && $teacher->id !== $adminUser->id) {
                    $participants[] = $teacher->id;
                }
            }

            foreach ($participants as $userId) {
                DB::table('chat_participants')->insert([
                    'conversation_id' => $convId,
                    'user_id'         => $userId,
                    'role'            => $userId === $adminUser->id ? 'admin' : 'member',
                    'joined_at'       => $now,
                    'created_at'      => $now, 'updated_at' => $now,
                ]);
            }

            // Seed sample messages
            $sectionMessages = [
                'Welcome to the ' . ($class->name ?? '') . ' Section ' . $section->name . ' group!',
                'Reminder: Unit test next week. Please prepare chapters 3 & 4.',
                'PTM is scheduled for Saturday 10 AM. All parents please attend.',
                'Homework for today: Complete exercise 5.2 from NCERT.',
                'Sports Day practice starts from Monday. All students must participate.',
            ];

            foreach ($sectionMessages as $mIdx => $msgBody) {
                $sender  = $participants[$mIdx % count($participants)];
                $msgTime = Carbon::now()->subDays(4 - $mIdx)->setHour(9 + $mIdx)->setMinute(rand(0, 59));

                $msgId = DB::table('chat_messages')->insertGetId([
                    'conversation_id' => $convId,
                    'sender_id'       => $sender,
                    'type'            => 'text',
                    'body'            => $msgBody,
                    'created_at'      => $msgTime, 'updated_at' => $msgTime,
                ]);
                $msgCount++;

                // Mark as read by other participants
                foreach ($participants as $readerId) {
                    if ($readerId !== $sender) {
                        DB::table('chat_message_reads')->insertOrIgnore([
                            'message_id' => $msgId,
                            'user_id'    => $readerId,
                            'read_at'    => $msgTime->addMinutes(rand(1, 30)),
                        ]);
                    }
                }
            }
        }

        // ── 2. Direct (1-to-1) Conversations between teachers ─────────────────
        $teacherList = $teachers->take(4)->values();
        for ($i = 0; $i < $teacherList->count() - 1; $i++) {
            $userA = $teacherList[$i];
            $userB = $teacherList[$i + 1];
            if (!$userA || !$userB) continue;

            $convId = DB::table('chat_conversations')->insertGetId([
                'school_id'         => $schoolId,
                'type'              => 'direct',
                'name'              => null,
                'is_system_managed' => false,
                'created_by'        => $userA->id,
                'created_at'        => $now, 'updated_at' => $now,
            ]);
            $convCount++;

            foreach ([$userA->id, $userB->id] as $uid) {
                DB::table('chat_participants')->insert([
                    'conversation_id' => $convId,
                    'user_id'         => $uid,
                    'role'            => 'member',
                    'joined_at'       => $now,
                    'created_at'      => $now, 'updated_at' => $now,
                ]);
            }

            $directMessages = [
                ['sender' => $userA->id, 'body' => 'Hi, can you share the lesson plan for this week?'],
                ['sender' => $userB->id, 'body' => 'Sure! I will send it by end of day.'],
                ['sender' => $userA->id, 'body' => 'Thanks. Also, are you free for the staff meeting tomorrow?'],
                ['sender' => $userB->id, 'body' => 'Yes, I will be there.'],
            ];

            foreach ($directMessages as $mIdx => $dm) {
                $msgTime = Carbon::now()->subHours(4 - $mIdx);
                DB::table('chat_messages')->insert([
                    'conversation_id' => $convId,
                    'sender_id'       => $dm['sender'],
                    'type'            => 'text',
                    'body'            => $dm['body'],
                    'created_at'      => $msgTime, 'updated_at' => $msgTime,
                ]);
                $msgCount++;
            }
        }

        // ── 3. Staff Announcement Group ────────────────────────────────────────
        if ($teachers->count() >= 2) {
            $staffConvId = DB::table('chat_conversations')->insertGetId([
                'school_id'         => $schoolId,
                'type'              => 'group',
                'name'              => 'All Staff — Announcements',
                'group_type'        => 'staff',
                'is_system_managed' => true,
                'is_pinned'         => true,
                'description'       => 'Official staff communication channel',
                'created_by'        => $adminUser->id,
                'created_at'        => $now, 'updated_at' => $now,
            ]);
            $convCount++;

            foreach ($teachers->take(6) as $t) {
                DB::table('chat_participants')->insertOrIgnore([
                    'conversation_id' => $staffConvId,
                    'user_id'         => $t->id,
                    'role'            => $t->id === $adminUser->id ? 'admin' : 'member',
                    'joined_at'       => $now,
                    'created_at'      => $now, 'updated_at' => $now,
                ]);
            }

            $staffMessages = [
                'Staff meeting this Friday at 4 PM in the conference room. Attendance is mandatory.',
                'Please submit your lesson plans for April by EOD tomorrow.',
                'Reminder: Exam duty roster has been shared. Check your allotted slots.',
                'New CBSE circular on assessment patterns — please review before Monday.',
            ];

            foreach ($staffMessages as $mIdx => $body) {
                $msgTime = Carbon::now()->subDays(3 - $mIdx);
                DB::table('chat_messages')->insert([
                    'conversation_id' => $staffConvId,
                    'sender_id'       => $adminUser->id,
                    'type'            => 'text',
                    'body'            => $body,
                    'created_at'      => $msgTime, 'updated_at' => $msgTime,
                ]);
                $msgCount++;
            }
        }

        $this->command->info('✅ Chat seeded!');
        $this->command->info("   - {$convCount} Conversations");
        $this->command->info("   - {$msgCount} Messages");
    }
}
