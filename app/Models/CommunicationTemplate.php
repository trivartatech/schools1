<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'type',
        'name',
        'slug',
        'template_id',
        'subject',
        'content',
        'audio_url',
        'is_active',
        'is_system',
        'variables',
        'language_code'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'variables' => 'json'
    ];

    /**
     * Master list of every system trigger and the channels each one supports.
     * Channels listed here MUST match the channels NotificationService actually
     * dispatches on for that trigger — otherwise the seeded row would never fire.
     *
     * Single source of truth used by:
     *  - CommunicationTemplateController (runtime auto-seed + Inertia prop for the UI)
     *  - The deploy-time seeding migration
     *  - CommunicationTemplateSeeder
     */
    public const SYSTEM_TRIGGERS = [
        'attendance_update' => [
            'name'      => 'Student Daily Attendance',
            'variables' => ['name', 'attendance', 'date', 'father_name', 'course_name', 'batch_name', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear ##FATHER_NAME##, your ward ##NAME## was marked ##ATTENDANCE## on ##DATE## at ##COURSE_NAME## - ##BATCH_NAME##.',
                // Meta-approved Utility template (Apr 2026). Variable order: name, attendance, date, course_name, batch_name, school_name.
                'whatsapp' => "Dear Parent,\n\nWe would like to inform you that ##NAME## was marked ##ATTENDANCE## on ##DATE## for ##COURSE_NAME## - ##BATCH_NAME##. Kindly contact the class teacher for any clarifications regarding attendance.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'voice'    => 'Dear parent, your ward ##NAME## was marked ##ATTENDANCE## on ##DATE##.',
                'push'     => '##NAME## was marked ##ATTENDANCE## on ##DATE##.',
            ],
            'subjects'  => ['push' => 'Attendance Update - ##NAME##'],
        ],
        'transport_attendance' => [
            'name'      => 'Transport Attendance',
            'variables' => ['name', 'status', 'trip_type', 'trip_label', 'stop_name', 'date', 'time', 'father_name', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'push'],
            'defaults'  => [
                'sms'      => 'Dear ##FATHER_NAME##, your ward ##NAME## has ##TRIP_LABEL## at ##STOP_NAME## on ##DATE## at ##TIME##.',
                // Meta-approved Utility template (Apr 2026). Variable order: name, trip_label, stop_name, date, time, school_name.
                'whatsapp' => "Dear Parent,\n\nThis is to inform you that your ward ##NAME## has ##TRIP_LABEL## at ##STOP_NAME## on ##DATE## at ##TIME##. Please ensure pick-up and drop-off arrangements are in place.\n\nRegards,\n##SCHOOL_NAME## Transport Team",
                'push'     => '##NAME## has ##TRIP_LABEL## at ##STOP_NAME##.',
            ],
            'subjects'  => ['push' => 'Transport Update - ##NAME##'],
        ],
        'fee_payment_confirmed' => [
            'name'      => 'Fee Payment Confirmed',
            'variables' => ['name', 'amount', 'receipt_no', 'datetime', 'payment_method', 'course_name', 'batch_name', 'school_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, Rs.##AMOUNT## fee received for ##NAME## (Receipt: ##RECEIPT_NO##) on ##DATETIME## via ##PAYMENT_METHOD##.',
                // Meta-approved Utility template (Apr 2026). Receipt number dropped for density compliance.
                // Variable order: amount, name, datetime, payment_method, school_name.
                'whatsapp' => "Dear Parent,\n\nWe have received Rs.##AMOUNT## towards school fee for ##NAME## on ##DATETIME## via ##PAYMENT_METHOD##. Thank you for the prompt payment.\n\n- ##SCHOOL_NAME## Administration",
                'voice'    => 'Dear parent, fee payment of rupees ##AMOUNT## has been received for ##NAME##. Receipt number ##RECEIPT_NO##.',
                'push'     => 'Rs.##AMOUNT## received for ##NAME##. Receipt: ##RECEIPT_NO##.',
            ],
            'subjects'  => ['push' => 'Fee Payment - ##NAME##'],
        ],
        'fee_due_reminder' => [
            'name'      => 'Fee Due Reminder',
            'variables' => ['name', 'amount', 'date', 'course_name', 'batch_name', 'school_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, fee of Rs.##AMOUNT## is due for ##NAME## (##COURSE_NAME##) by ##DATE##. Please pay at the earliest.',
                // Meta-approved Utility template (Apr 2026). Variable order: amount, name, course_name, date, school_name.
                'whatsapp' => "Dear Parent,\n\nThis is a gentle reminder that school fee of Rs.##AMOUNT## is due for ##NAME## (##COURSE_NAME##) by ##DATE##. Please make the payment at the earliest to avoid late fee charges.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'voice'    => 'Dear parent, fee of rupees ##AMOUNT## is due for ##NAME## by ##DATE##. Please pay at the earliest.',
                'push'     => 'Fee of Rs.##AMOUNT## is due for ##NAME## by ##DATE##.',
            ],
            'subjects'  => ['push' => 'Fee Due - ##NAME##'],
        ],
        'diary_created' => [
            'name'      => 'New Diary Entry',
            'variables' => ['name', 'subject', 'date', 'course_name', 'batch_name', 'app_name'],
            'channels'  => ['push'],
            'defaults'  => [
                'push' => 'A new diary entry has been added for ##NAME## (##SUBJECT##) on ##DATE##.',
            ],
            'subjects'  => ['push' => 'New Diary Entry - ##NAME##'],
        ],
        'assignment_created' => [
            'name'      => 'New Assignment',
            'variables' => ['name', 'title', 'subject', 'due_date', 'course_name', 'batch_name', 'app_name'],
            'channels'  => ['push'],
            'defaults'  => [
                'push' => '##TITLE## (##SUBJECT##) for ##NAME##. Due ##DUE_DATE##.',
            ],
            'subjects'  => ['push' => 'New Assignment - ##TITLE##'],
        ],
        'otp' => [
            'name'      => 'Login OTP',
            'variables' => ['otp', 'app_name'],
            'channels'  => ['sms'],
            'defaults'  => [
                'sms' => 'Your OTP for ##APP_NAME## is ##OTP##. Do not share this with anyone.',
            ],
        ],
        'exam_published' => [
            'name'      => 'Exam Schedule Published',
            'variables' => ['name', 'title', 'datetime', 'class_name', 'type', 'school_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the portal for details.',
                // Meta-approved Utility template (Apr 2026). Variable order: title, class_name, school_name.
                'whatsapp' => "Dear Parent,\n\nThe ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the school portal or contact the class teacher for further details and timetable.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'voice'    => 'Dear parent, the ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the school portal.',
                'push'     => '##TITLE## exam schedule for ##CLASS_NAME## is now available.',
            ],
            'subjects'  => ['push' => 'Exam Published - ##TITLE##'],
        ],
        'test_sms' => [
            'name'      => 'Test SMS Notification',
            'variables' => ['name', 'date', 'app_name'],
            'channels'  => ['sms'],
            'defaults'  => [
                'sms' => 'Hi ##NAME##, this is a test message from ##APP_NAME## sent on ##DATE##.',
            ],
        ],
        'daily_report' => [
            'name'      => 'Daily Master Report',
            'variables' => ['app_name', 'date', 'caption', 'link', 'school_name'],
            'channels'  => ['sms', 'whatsapp'],
            'defaults'  => [
                'sms'      => '##APP_NAME## Daily Report ##DATE##: ##CAPTION##. Full report: ##LINK##',
                // Meta-approved Utility template (Apr 2026). Reshaped to satisfy
                // no-variable-at-start rule. Variable order: date, caption, link, school_name.
                'whatsapp' => "Dear Admin,\n\nThe Daily Master Report for ##DATE## is ready. ##CAPTION## Please open the full PDF report at ##LINK## for detailed insights.\n\nRegards,\n##SCHOOL_NAME## Administration",
            ],
        ],
        'admission_confirmed' => [
            'name'      => 'Admission Confirmed',
            'variables' => ['name', 'course_name', 'batch_name', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Welcome ##NAME##! Admission to ##COURSE_NAME## - ##BATCH_NAME## at ##SCHOOL_NAME## is confirmed.',
                // Meta-approved Utility template style. Variable order: name, course_name, batch_name, school_name.
                'whatsapp' => "Dear Parent,\n\nWe are pleased to confirm that the admission of ##NAME## to ##COURSE_NAME## - ##BATCH_NAME## has been successfully processed. Please contact the front office for any further details on orientation and required documents.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'voice'    => 'Dear parent, the admission of ##NAME## to ##COURSE_NAME## has been confirmed. Welcome to ##SCHOOL_NAME##.',
                'push'     => 'Admission confirmed for ##NAME## (##COURSE_NAME##).',
            ],
            'subjects'  => ['push' => 'Admission Confirmed - ##NAME##'],
        ],
        'holiday_notice' => [
            'name'      => 'Holiday Notice',
            'variables' => ['date', 'reason', 'resume_date', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'push'],
            'defaults'  => [
                'sms'      => 'Notice: ##SCHOOL_NAME## will be closed on ##DATE## (##REASON##). Classes resume on ##RESUME_DATE##.',
                // Meta-approved Utility template style. Variable order: school_name, date, reason, resume_date.
                'whatsapp' => "Dear Parent,\n\nThis is to inform you that ##SCHOOL_NAME## will remain closed on ##DATE## on account of ##REASON##. Regular classes will resume on ##RESUME_DATE##. Please plan your child's schedule accordingly.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'push'     => 'School closed on ##DATE## (##REASON##).',
            ],
            'subjects'  => ['push' => 'Holiday Notice - ##DATE##'],
        ],
        'parent_meeting' => [
            'name'      => 'Parent-Teacher Meeting',
            'variables' => ['name', 'date', 'time', 'class_name', 'venue', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, PTM for ##NAME## (##CLASS_NAME##) on ##DATE## at ##TIME##, venue: ##VENUE##.',
                // Meta-approved Utility template style. Variable order: name, class_name, date, time, venue, school_name.
                'whatsapp' => "Dear Parent,\n\nA Parent-Teacher Meeting is scheduled for ##NAME## of ##CLASS_NAME## on ##DATE## at ##TIME##. The meeting will be held at ##VENUE##. Your presence is highly appreciated to discuss your child's academic progress.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'voice'    => 'Dear parent, the parent-teacher meeting for ##NAME## of ##CLASS_NAME## is scheduled on ##DATE## at ##TIME##. Please attend.',
                'push'     => 'PTM ##DATE## at ##TIME## for ##NAME## (##CLASS_NAME##).',
            ],
            'subjects'  => ['push' => 'Parent-Teacher Meeting - ##DATE##'],
        ],
        'result_published' => [
            'name'      => 'Exam Results Published',
            'variables' => ['name', 'exam_name', 'class_name', 'portal_url', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, ##EXAM_NAME## results for ##NAME## (##CLASS_NAME##) are published. View at ##PORTAL_URL##.',
                // Meta-approved Utility template style. Variable order: exam_name, name, class_name, portal_url, school_name.
                'whatsapp' => "Dear Parent,\n\nThe ##EXAM_NAME## results for ##NAME## of ##CLASS_NAME## have been published. You can view the detailed marksheet by logging in to the school portal at ##PORTAL_URL##. For any clarifications, please contact the class teacher.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'voice'    => 'Dear parent, the ##EXAM_NAME## results for ##NAME## have been published. Please check the school portal.',
                'push'     => '##EXAM_NAME## results published for ##NAME##.',
            ],
            'subjects'  => ['push' => 'Results Published - ##EXAM_NAME##'],
        ],
        'emergency_alert' => [
            'name'      => 'Emergency Alert',
            'variables' => ['message', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'URGENT from ##SCHOOL_NAME##: ##MESSAGE## Please contact the school office immediately.',
                // Meta-approved Utility template style. Variable order: school_name, message, school_name.
                'whatsapp' => "Dear Parent,\n\nThis is an urgent notice from ##SCHOOL_NAME##. ##MESSAGE## Please follow the instructions immediately and contact the school office for any clarifications or assistance.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'voice'    => 'This is an urgent notice from ##SCHOOL_NAME##. ##MESSAGE## Please contact the school immediately.',
                'push'     => 'URGENT: ##MESSAGE##',
            ],
            'subjects'  => ['push' => 'Urgent Notice - ##SCHOOL_NAME##'],
        ],
        'birthday_wishes' => [
            'name'      => 'Birthday Wishes',
            'variables' => ['name', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'push'],
            'defaults'  => [
                'sms'      => 'Wishing ##NAME## a very happy birthday from the entire ##SCHOOL_NAME## family!',
                // Meta-approved Utility template style. Variable order: name, school_name, school_name.
                'whatsapp' => "Dear ##NAME##,\n\nThe entire ##SCHOOL_NAME## family wishes you a very happy birthday! May this special day bring you joy, success, and wonderful memories. Have a fantastic year ahead filled with learning and achievements.\n\nWith warm wishes,\n##SCHOOL_NAME## Administration",
                'push'     => 'Happy Birthday, ##NAME##!',
            ],
            'subjects'  => ['push' => 'Happy Birthday, ##NAME##!'],
        ],
        'general_announcement' => [
            'name'      => 'General Announcement',
            'variables' => ['title', 'message', 'school_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'push'],
            'defaults'  => [
                'sms'      => '##SCHOOL_NAME##: ##TITLE## - ##MESSAGE##',
                // Meta-approved Utility template style. Variable order: title, message, school_name.
                'whatsapp' => "Dear Parent,\n\n##TITLE##\n\n##MESSAGE##\n\nFor any queries or further information, please contact the school office. Thank you for your continued support.\n\nRegards,\n##SCHOOL_NAME## Administration",
                'push'     => '##TITLE##: ##MESSAGE##',
            ],
            'subjects'  => ['push' => '##TITLE##'],
        ],
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Idempotent: creates one row per (school × trigger × channel) combination
     * for every entry in SYSTEM_TRIGGERS, then backfills is_system=true on any
     * legacy rows whose slug matches a known system trigger but were inserted
     * before the flag existed.
     */
    public static function seedSystemTemplatesForSchool(int $schoolId): void
    {
        foreach (self::SYSTEM_TRIGGERS as $slug => $config) {
            foreach ($config['channels'] as $type) {
                self::firstOrCreate(
                    ['school_id' => $schoolId, 'type' => $type, 'slug' => $slug],
                    [
                        'name'          => $config['name'],
                        'is_system'     => true,
                        'is_active'     => true,
                        'variables'     => $config['variables'],
                        'content'       => $config['defaults'][$type] ?? "Default {$config['name']} message.",
                        'subject'       => $config['subjects'][$type] ?? null,
                        'audio_url'     => $config['audio_urls'][$type] ?? null,
                        'language_code' => $type === 'whatsapp' ? 'en' : null,
                    ]
                );
            }
        }

        self::where('school_id', $schoolId)
            ->whereIn('slug', array_keys(self::SYSTEM_TRIGGERS))
            ->where('is_system', false)
            ->update(['is_system' => true]);
    }
}
