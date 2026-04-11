<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CommunicationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolId = \App\Models\School::first()->id;

        $templates = [
            // SMS
            [
                'school_id' => $schoolId,
                'type' => 'sms',
                'name' => 'Fee Due Reminder',
                'slug' => 'fee_due_reminder',
                'content' => 'Hi ##NAME##, This is a gentle reminder to pay your fee of ##AMOUNT## due on ##DUE_DATE## for ##COURSE## ##BATCH##. Late fee is applicable for overdues payment. ##APP_NAME##',
                'is_active' => false,
            ],
            [
                'school_id' => $schoolId,
                'type' => 'sms',
                'name' => 'Student Daily Attendance',
                'slug' => 'attendance_update',
                'template_id' => '68b5407bba3f5c5d622d94c7',
                'content' => 'Hi ##NAME##, Your attendance is marked as ##ATTENDANCE## on ##DATE## for ##COURSE_NAME## ##BATCH_NAME##. ##APP_NAME##',
                'is_active' => true,
            ],
            [
                'school_id' => $schoolId,
                'type' => 'sms',
                'name' => 'OTP',
                'slug' => 'otp',
                'content' => 'Your one time password is ##CODE## valid for ##TOKEN_LIFETIME## minutes. Do not share this code with anyone else.',
                'is_active' => false,
            ],

            // WhatsApp
            [
                'school_id' => $schoolId,
                'type' => 'whatsapp',
                'name' => 'Student Daily Attendance',
                'slug' => 'attendance_update',
                'template_id' => 'attendance_update_v2',
                'content' => 'Dear ##FATHER_NAME##, Your child ##NAME## Class ##COURSE_NAME##, Section ##BATCH_NAME## is ##ATTENDANCE## on ##DATE##. - ##APP_NAME## Service provided by Trivarta Tech Pvt Ltd',
                'is_active' => true,
            ],
            [
                'school_id' => $schoolId,
                'type' => 'whatsapp',
                'name' => 'Fee Payment Confirmed',
                'slug' => 'fee_payment_confirmed',
                'template_id' => 'fee_payment_confirmed_v1',
                'content' => 'Hi ##NAME##, Your fee payment of ##AMOUNT## for ##COURSE_NAME## ##BATCH_NAME## with receipt number ##RECEIPT_NO## at ##DATETIME## using ##PAYMENT_METHOD## is confirmed. ##APP_NAME##',
                'is_active' => true,
            ],
            [
                'school_id' => $schoolId,
                'type' => 'whatsapp',
                'name' => 'Fee Due Reminder',
                'slug' => 'fee_due_reminder',
                'template_id' => 'fee_due_reminder_v1',
                'content' => 'Dear Parent, this is a reminder that fee amount of Rs. ##AMOUNT## is due on ##DATE## for ##NAME## (##COURSE_NAME## ##BATCH_NAME##). - ##APP_NAME##',
                'is_active' => true,
            ],

            // Exam
            [
                'school_id' => $schoolId,
                'type' => 'sms',
                'name' => 'Exam Schedule Published',
                'slug' => 'exam_published',
                'content' => 'Hi ##NAME##, The schedule for ##TITLE## has been published. Please check the portal for details. ##APP_NAME##',
                'is_active' => true,
            ],
            [
                'school_id' => $schoolId,
                'type' => 'whatsapp',
                'name' => 'Exam Schedule Published',
                'slug' => 'exam_published',
                'content' => 'Dear Parent, The exam schedule for ##TITLE## (##CLASS_NAME##) is now available. Date: ##DATETIME##. - ##APP_NAME##',
                'is_active' => true,
            ],

            // Push/Portal Notifications
            [
                'school_id' => $schoolId,
                'type' => 'push',
                'name' => 'Attendance Update',
                'slug' => 'attendance_update',
                'subject' => 'Attendance Update: ##ATTENDANCE##',
                'content' => 'Your ward ##NAME## is marked ##ATTENDANCE## on ##DATE##.',
                'is_active' => true,
            ],
            [
                'school_id' => $schoolId,
                'type' => 'push',
                'name' => 'Fee Payment Receipt',
                'slug' => 'fee_payment_confirmed',
                'subject' => 'Fee Payment Confirmed',
                'content' => 'Payment of ##AMOUNT## received successfully on ##DATETIME##. Receipt No: ##RECEIPT_NO##',
                'is_active' => true,
            ],
            [
                'school_id' => $schoolId,
                'type' => 'push',
                'name' => 'Exam Notification',
                'slug' => 'exam_published',
                'subject' => 'Exam Schedule Published',
                'content' => 'The exam schedule for ##TITLE## has been published.',
                'is_active' => true,
            ],

            // Voice
            [
                'school_id' => $schoolId,
                'type' => 'voice',
                'name' => 'Attendance Call (Intro + TTS)',
                'slug' => 'attendance_update',
                'audio_url' => 'https://trivarta.com/assets/audio/attendance_intro.mp3',
                'content' => 'Dear parent, your ward ##NAME## is ##ATTENDANCE## today on ##DATE##. Please contact the office for more details.',
                'is_active' => true,
            ]
        ];

        foreach ($templates as $tpl) {
            $tpl['is_system'] = true;
            \App\Models\CommunicationTemplate::updateOrCreate(
                ['school_id' => $tpl['school_id'], 'slug' => $tpl['slug'], 'type' => $tpl['type']],
                $tpl
            );
        }
    }
}
