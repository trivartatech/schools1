<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $adminUserId = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['principal', 'admin', 'school_admin'])
            ->value('id') ?? DB::table('users')->where('school_id', $schoolId)->value('id');

        if (!$adminUserId) {
            $this->command->error('CertificateSeeder: no users in school; skipping.');
            return;
        }

        Schema::disableForeignKeyConstraints();
        DB::table('certificate_issuances')->where('school_id', $schoolId)->delete();
        DB::table('certificate_templates')->where('school_id', $schoolId)->delete();
        DB::table('id_card_templates')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Certificate templates (3) ──────────────────────────────────────
        $defaultBg = json_encode(['front' => ['type' => 'color', 'value' => '#ffffff']]);
        $defaultElements = json_encode([
            ['type' => 'text', 'x' => 50,  'y' => 80,  'w' => 600, 'h' => 50,  'content' => '{{school_name}}', 'style' => ['fontSize' => 28, 'fontWeight' => 'bold', 'textAlign' => 'center']],
            ['type' => 'text', 'x' => 50,  'y' => 160, 'w' => 600, 'h' => 30,  'content' => 'Certificate of Achievement',  'style' => ['fontSize' => 20, 'textAlign' => 'center']],
            ['type' => 'text', 'x' => 50,  'y' => 220, 'w' => 600, 'h' => 30,  'content' => 'This is to certify that',     'style' => ['fontSize' => 14, 'textAlign' => 'center']],
            ['type' => 'text', 'x' => 50,  'y' => 260, 'w' => 600, 'h' => 40,  'content' => '{{student_name}}',             'style' => ['fontSize' => 24, 'fontWeight' => 'bold', 'textAlign' => 'center']],
            ['type' => 'text', 'x' => 50,  'y' => 320, 'w' => 600, 'h' => 60,  'content' => 'has successfully {{achievement}}.', 'style' => ['fontSize' => 14, 'textAlign' => 'center']],
            ['type' => 'text', 'x' => 50,  'y' => 420, 'w' => 600, 'h' => 30,  'content' => 'Date: {{issue_date}}',         'style' => ['fontSize' => 12, 'textAlign' => 'center']],
        ]);

        $templates = [
            [
                'name' => 'Achievement Certificate',
                'orientation' => 'landscape',
                'custom_vars' => [
                    ['key' => 'achievement', 'label' => 'Achievement', 'placeholder' => 'completed the course on Robotics'],
                ],
            ],
            [
                'name' => 'Participation Certificate',
                'orientation' => 'landscape',
                'custom_vars' => [
                    ['key' => 'event', 'label' => 'Event', 'placeholder' => 'Annual Sports Day 2026'],
                ],
            ],
            [
                'name' => 'Merit Certificate',
                'orientation' => 'portrait',
                'custom_vars' => [
                    ['key' => 'subject', 'label' => 'Subject', 'placeholder' => 'Mathematics'],
                    ['key' => 'rank',    'label' => 'Rank',    'placeholder' => '1st'],
                ],
            ],
        ];

        $templateIds = [];
        foreach ($templates as $t) {
            $templateIds[] = DB::table('certificate_templates')->insertGetId([
                'school_id'   => $schoolId,
                'created_by'  => $adminUserId,
                'name'        => $t['name'],
                'orientation' => $t['orientation'],
                'background'  => $defaultBg,
                'elements'    => $defaultElements,
                'custom_vars' => json_encode($t['custom_vars']),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // ── 2. ID card templates (2) ──────────────────────────────────────────
        $idCardBg = json_encode(['type' => 'color', 'value' => '#1e40af']);
        $idCardElements = json_encode([
            ['type' => 'text',  'x' => 10, 'y' => 10, 'w' => 240, 'h' => 30, 'content' => '{{school_name}}',  'style' => ['fontSize' => 14, 'fontWeight' => 'bold', 'color' => '#ffffff']],
            ['type' => 'image', 'x' => 10, 'y' => 50, 'w' => 80,  'h' => 100, 'content' => '{{student_photo}}'],
            ['type' => 'text',  'x' => 100,'y' => 50, 'w' => 150, 'h' => 24, 'content' => '{{student_name}}', 'style' => ['fontSize' => 14, 'fontWeight' => 'bold']],
            ['type' => 'text',  'x' => 100,'y' => 80, 'w' => 150, 'h' => 20, 'content' => 'Class: {{class}}', 'style' => ['fontSize' => 11]],
            ['type' => 'text',  'x' => 100,'y' => 105,'w' => 150, 'h' => 20, 'content' => 'Roll: {{roll_no}}','style' => ['fontSize' => 11]],
            ['type' => 'text',  'x' => 10, 'y' => 165,'w' => 240, 'h' => 20, 'content' => 'Adm. No: {{admission_no}}', 'style' => ['fontSize' => 10, 'color' => '#ffffff']],
        ]);

        $idCardTemplates = [
            ['name' => 'Student ID Card — Standard',  'orientation' => 'portrait', 'columns' => 2],
            ['name' => 'Staff ID Card — Lanyard',      'orientation' => 'landscape','columns' => 2],
        ];
        foreach ($idCardTemplates as $t) {
            DB::table('id_card_templates')->insert([
                'school_id'    => $schoolId,
                'created_by'   => $adminUserId,
                'name'         => $t['name'],
                'orientation'  => $t['orientation'],
                'background'   => $idCardBg,
                'elements'     => $idCardElements,
                'columns'      => $t['columns'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // ── 3. Certificate issuances (~10 to misc students) ───────────────────
        $studentIds = DB::table('students')->where('school_id', $schoolId)->limit(10)->pluck('id')->toArray();
        if (empty($studentIds) || empty($templateIds)) {
            $this->command->info('CertificateSeeder: templates seeded; no students for issuances.');
            return;
        }

        $achievements = [
            'Topped the class in Mathematics',
            'Completed the Robotics workshop',
            'Won inter-school debate',
            'Participated in Annual Sports Day',
            'Excellent academic performance',
            'Won the Quiz Competition',
            'Best discipline award',
            'Perfect attendance for the year',
            'Best Student in Science',
            'Outstanding participation in cultural events',
        ];

        foreach ($studentIds as $i => $sid) {
            $tplId = $templateIds[array_rand($templateIds)];
            DB::table('certificate_issuances')->insert([
                'school_id'          => $schoolId,
                'template_id'        => $tplId,
                'student_id'         => $sid,
                'certificate_no'     => 'CERT/' . date('Y') . '/' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'issued_date'        => $now->copy()->subDays(rand(5, 90))->format('Y-m-d'),
                'custom_vals'        => json_encode(['achievement' => $achievements[$i % count($achievements)]]),
                'verification_token' => Str::random(48),
                'issued_by'          => $adminUserId,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }

        $this->command->info('✅ Certificates seeded: 3 cert templates, 2 ID-card templates, 10 issuances.');
    }
}
