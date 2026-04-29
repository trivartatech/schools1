<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class PostsSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        Schema::disableForeignKeyConstraints();
        DB::table('post_bookmarks')->whereIn('post_id',
            DB::table('posts')->where('school_id', $schoolId)->pluck('id')
        )->delete();
        DB::table('post_comments')->whereIn('post_id',
            DB::table('posts')->where('school_id', $schoolId)->pluck('id')
        )->delete();
        DB::table('post_likes')->whereIn('post_id',
            DB::table('posts')->where('school_id', $schoolId)->pluck('id')
        )->delete();
        DB::table('post_media')->whereIn('post_id',
            DB::table('posts')->where('school_id', $schoolId)->pluck('id')
        )->delete();
        DB::table('posts')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        $allUsers = DB::table('users')->where('school_id', $schoolId)->pluck('id')->toArray();
        if (count($allUsers) < 2) {
            $this->command->info('PostsSeeder: not enough users; skipping.');
            return;
        }

        $teacherUserIds = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['teacher', 'principal', 'admin', 'school_admin'])
            ->pluck('id')->toArray();
        if (empty($teacherUserIds)) $teacherUserIds = $allUsers;

        $samplePosts = [
            ['type' => 'announcement', 'visibility' => 'school', 'content' => 'Annual Day will be celebrated on Saturday, 15th February. All students must report by 8:30 AM in school uniform.'],
            ['type' => 'achievement',  'visibility' => 'school', 'content' => 'Congratulations to our Class 10 students for outstanding results in the recent Olympiad! 🎉'],
            ['type' => 'update',        'visibility' => 'school', 'content' => 'Library has added 50+ new books across various genres. Visit the library to explore.'],
            ['type' => 'event',         'visibility' => 'school', 'content' => 'Inter-house Football tournament begins next Monday. Best of luck to all houses!'],
            ['type' => 'announcement',  'visibility' => 'school', 'content' => 'Parent-teacher meeting scheduled for the upcoming Saturday. Slot booking opens tomorrow.'],
            ['type' => 'photo',         'visibility' => 'school', 'content' => 'Glimpses from yesterday\'s science exhibition. Wonderful projects by our young scientists!'],
            ['type' => 'achievement',   'visibility' => 'school', 'content' => 'Aravalli House lifted the Inter-house Cricket Trophy this year. Congratulations team!'],
            ['type' => 'update',        'visibility' => 'school', 'content' => 'School transport routes updated for the new term. Check the Transport tab for details.'],
            ['type' => 'event',         'visibility' => 'school', 'content' => 'Yoga Day celebration on 21st June. All students and staff invited to the morning assembly.'],
            ['type' => 'announcement',  'visibility' => 'school', 'content' => 'Mid-term examinations begin from next Monday. Time-table available in the Examinations tab.'],
            ['type' => 'photo',         'visibility' => 'school', 'content' => 'Our students participated in the inter-school debate competition and brought home laurels.'],
            ['type' => 'update',        'visibility' => 'school', 'content' => 'Hostel mess menu refreshed for the new term. Check it out!'],
            ['type' => 'achievement',   'visibility' => 'school', 'content' => 'Kudos to the Robotics club for winning the State-level competition!'],
            ['type' => 'event',         'visibility' => 'school', 'content' => 'Career counselling workshop for Class 11 & 12 on this Friday at 11 AM.'],
            ['type' => 'announcement',  'visibility' => 'school', 'content' => 'School will remain closed on 15th August for Independence Day. Flag hoisting at 8 AM in the morning.'],
        ];

        $tags = [
            ['#announcement'],
            ['#achievement', '#congratulations'],
            ['#event'],
            ['#update'],
            ['#sports'],
            ['#academics'],
        ];

        $postIds = [];
        foreach ($samplePosts as $i => $p) {
            $authorId = $teacherUserIds[array_rand($teacherUserIds)];
            $isPinned = $i < 2; // pin first two posts

            $postIds[] = DB::table('posts')->insertGetId([
                'school_id'    => $schoolId,
                'user_id'      => $authorId,
                'content'      => $p['content'],
                'visibility'   => $p['visibility'],
                'type'         => $p['type'],
                'tags'         => json_encode($tags[array_rand($tags)]),
                'class_id'     => null,
                'is_approved'  => true,
                'is_pinned'    => $isPinned,
                'pinned_at'    => $isPinned ? $now : null,
                'pinned_by'    => $isPinned ? $authorId : null,
                'shares_count' => rand(0, 8),
                'created_at'   => $now->copy()->subDays(rand(0, 30)),
                'updated_at'   => $now,
            ]);
        }

        // ── Likes (~50 across posts) ──────────────────────────────────────────
        $likeRows = [];
        $seen = [];
        for ($i = 0; $i < 80; $i++) {
            $pid = $postIds[array_rand($postIds)];
            $uid = $allUsers[array_rand($allUsers)];
            $key = $pid . ':' . $uid;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $likeRows[] = [
                'post_id'    => $pid,
                'user_id'    => $uid,
                'type'       => 'like',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($likeRows) DB::table('post_likes')->insert($likeRows);

        // ── Comments (~30) ────────────────────────────────────────────────────
        $sampleComments = [
            'Great news!',
            'Congratulations!',
            'Looking forward to it.',
            'When is the next event?',
            'Proud of our students.',
            'Excellent work!',
            'Thanks for the update.',
            'Will the schedule be shared?',
            'All the best!',
            'Way to go!',
        ];
        for ($i = 0; $i < 30; $i++) {
            DB::table('post_comments')->insert([
                'post_id'    => $postIds[array_rand($postIds)],
                'user_id'    => $allUsers[array_rand($allUsers)],
                'parent_id'  => null,
                'comment'    => $sampleComments[array_rand($sampleComments)],
                'created_at' => $now->copy()->subDays(rand(0, 25)),
                'updated_at' => $now,
            ]);
        }

        $this->command->info('✅ Posts seeded: ' . count($samplePosts) . ' posts, ~80 likes, 30 comments.');
    }
}
