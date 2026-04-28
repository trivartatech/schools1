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
            'variables' => ['name', 'attendance', 'date', 'father_name', 'course_name', 'batch_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear ##FATHER_NAME##, your ward ##NAME## was marked ##ATTENDANCE## on ##DATE## at ##COURSE_NAME## - ##BATCH_NAME##.',
                'whatsapp' => 'Dear ##FATHER_NAME##, your ward ##NAME## was marked ##ATTENDANCE## on ##DATE## at ##COURSE_NAME## - ##BATCH_NAME##.',
                'voice'    => 'Dear parent, your ward ##NAME## was marked ##ATTENDANCE## on ##DATE##.',
                'push'     => '##NAME## was marked ##ATTENDANCE## on ##DATE##.',
            ],
            'subjects'  => ['push' => 'Attendance Update - ##NAME##'],
        ],
        'transport_attendance' => [
            'name'      => 'Transport Attendance',
            'variables' => ['name', 'status', 'trip_type', 'trip_label', 'stop_name', 'date', 'time', 'father_name', 'app_name'],
            'channels'  => ['sms', 'whatsapp', 'push'],
            'defaults'  => [
                'sms'      => 'Dear ##FATHER_NAME##, your ward ##NAME## has ##TRIP_LABEL## at ##STOP_NAME## on ##DATE## at ##TIME##.',
                'whatsapp' => 'Dear ##FATHER_NAME##, your ward ##NAME## has ##TRIP_LABEL## at ##STOP_NAME## on ##DATE## at ##TIME##.',
                'push'     => '##NAME## has ##TRIP_LABEL## at ##STOP_NAME##.',
            ],
            'subjects'  => ['push' => 'Transport Update - ##NAME##'],
        ],
        'fee_payment_confirmed' => [
            'name'      => 'Fee Payment Confirmed',
            'variables' => ['name', 'amount', 'receipt_no', 'datetime', 'payment_method', 'course_name', 'batch_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, Rs.##AMOUNT## fee received for ##NAME## (Receipt: ##RECEIPT_NO##) on ##DATETIME## via ##PAYMENT_METHOD##.',
                'whatsapp' => 'Dear Parent, Rs.##AMOUNT## fee received for ##NAME## (Receipt: ##RECEIPT_NO##) on ##DATETIME## via ##PAYMENT_METHOD##.',
                'voice'    => 'Dear parent, fee payment of rupees ##AMOUNT## has been received for ##NAME##. Receipt number ##RECEIPT_NO##.',
                'push'     => 'Rs.##AMOUNT## received for ##NAME##. Receipt: ##RECEIPT_NO##.',
            ],
            'subjects'  => ['push' => 'Fee Payment - ##NAME##'],
        ],
        'fee_due_reminder' => [
            'name'      => 'Fee Due Reminder',
            'variables' => ['name', 'amount', 'date', 'course_name', 'batch_name'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, fee of Rs.##AMOUNT## is due for ##NAME## (##COURSE_NAME##) by ##DATE##. Please pay at the earliest.',
                'whatsapp' => 'Dear Parent, fee of Rs.##AMOUNT## is due for ##NAME## (##COURSE_NAME##) by ##DATE##. Please pay at the earliest.',
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
            'variables' => ['name', 'title', 'datetime', 'class_name', 'type'],
            'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
            'defaults'  => [
                'sms'      => 'Dear Parent, ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the portal for details.',
                'whatsapp' => 'Dear Parent, ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the portal for details.',
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
