<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnnouncementsSeeder extends Seeder
{
    public function run(): void
    {
        $school      = DB::table('schools')->first();
        $schoolId    = $school->id;
        $now         = Carbon::now();
        $senderId    = DB::table('users')->where('school_id', $schoolId)->whereIn('user_type', ['principal', 'admin'])->value('id');
        $templateId  = DB::table('communication_templates')->where('school_id', $schoolId)->value('id');

        DB::table('announcements')->where('school_id', $schoolId)->delete();
        DB::table('communication_logs')->where('school_id', $schoolId)->delete();

        // ── 1. Announcements ───────────────────────────────────────────────────
        $announcements = [
            [
                'title'           => 'PTM Scheduled — Saturday 19 April 2026',
                'delivery_method' => 'sms',
                'audience_type'   => 'school',
                'audience_ids'    => null,
                'is_broadcasted'  => true,
                'scheduled_at'    => Carbon::now()->subDays(5),
            ],
            [
                'title'           => 'School Closed — Ram Navami Holiday',
                'delivery_method' => 'whatsapp',
                'audience_type'   => 'school',
                'audience_ids'    => null,
                'is_broadcasted'  => true,
                'scheduled_at'    => Carbon::now()->subDays(10),
            ],
            [
                'title'           => 'Annual Sports Day — 25 April 2026',
                'delivery_method' => 'sms',
                'audience_type'   => 'school',
                'audience_ids'    => null,
                'is_broadcasted'  => true,
                'scheduled_at'    => Carbon::now()->subDays(3),
            ],
            [
                'title'           => 'Fee Due Reminder — Last Date 30 April',
                'delivery_method' => 'sms',
                'audience_type'   => 'school',
                'audience_ids'    => null,
                'is_broadcasted'  => true,
                'scheduled_at'    => Carbon::now()->subDays(2),
            ],
            [
                'title'           => 'Science Exhibition — Entries Open',
                'delivery_method' => 'whatsapp',
                'audience_type'   => 'class',
                'audience_ids'    => json_encode([1, 2, 3]),
                'is_broadcasted'  => false,
                'scheduled_at'    => Carbon::now()->addDays(2),
            ],
            [
                'title'           => 'Class 10 Board Exam Preparation Tips',
                'delivery_method' => 'sms',
                'audience_type'   => 'class',
                'audience_ids'    => json_encode([10]),
                'is_broadcasted'  => true,
                'scheduled_at'    => Carbon::now()->subDays(1),
            ],
            [
                'title'           => 'Staff Meeting — Monday 6 PM',
                'delivery_method' => 'sms',
                'audience_type'   => 'employee',
                'audience_ids'    => null,
                'is_broadcasted'  => true,
                'scheduled_at'    => Carbon::now()->subDays(4),
            ],
            [
                'title'           => 'Result Declaration — SA1 Exam',
                'delivery_method' => 'whatsapp',
                'audience_type'   => 'school',
                'audience_ids'    => null,
                'is_broadcasted'  => false,
                'scheduled_at'    => Carbon::now()->addDays(5),
            ],
        ];

        foreach ($announcements as $a) {
            DB::table('announcements')->insert([
                'school_id'                 => $schoolId,
                'sender_id'                 => $senderId,
                'title'                     => $a['title'],
                'delivery_method'           => $a['delivery_method'],
                'audience_type'             => $a['audience_type'],
                'audience_ids'              => $a['audience_ids'],
                'communication_template_id' => $templateId,
                'is_broadcasted'            => $a['is_broadcasted'],
                'scheduled_at'              => $a['scheduled_at'],
                'broadcast_error'           => null,
                'created_at'                => $a['scheduled_at'],
                'updated_at'                => $a['scheduled_at'],
            ]);
        }

        // ── 2. Communication Logs ──────────────────────────────────────────────
        $logTypes     = ['sms', 'whatsapp', 'email'];
        $providers    = ['Twilio', 'MSG91', 'SendGrid'];
        $logStatuses  = ['sent', 'sent', 'delivered', 'failed'];
        $messages     = [
            'PTM reminder: Parent-Teacher Meeting scheduled on 19th April at school premises.',
            'Holiday notice: School will remain closed on Ram Navami (14 April).',
            'Annual Sports Day on 25 April. Students must wear sports uniform.',
            'Fee reminder: Q1 fee due by 30 April. Pay online at portal.',
            'SA1 Results declared. Check student portal for marks.',
        ];

        $users = DB::table('users')->where('school_id', $schoolId)->take(10)->pluck('id')->toArray();

        for ($i = 0; $i < 20; $i++) {
            $type     = $logTypes[$i % count($logTypes)];
            $status   = $logStatuses[$i % count($logStatuses)];
            $userId   = $users[$i % count($users)] ?? null;
            $sentAt   = Carbon::now()->subDays(rand(0, 30));

            DB::table('communication_logs')->insert([
                'school_id'         => $schoolId,
                'user_id'           => $userId,
                'type'              => $type,
                'provider'          => $providers[$i % count($providers)],
                'to'                => $type === 'email' ? 'user' . $i . '@example.com' : '98' . rand(10000000, 99999999),
                'message'           => $messages[$i % count($messages)],
                'status'            => $status,
                'provider_response' => json_encode(['status' => $status, 'message_id' => 'MSG' . rand(100000, 999999)]),
                'created_at'        => $sentAt, 'updated_at' => $sentAt,
            ]);
        }

        $this->command->info('✅ Announcements seeded!');
        $this->command->info('   - ' . count($announcements) . ' Announcements');
        $this->command->info('   - 20 Communication Logs');
    }
}
