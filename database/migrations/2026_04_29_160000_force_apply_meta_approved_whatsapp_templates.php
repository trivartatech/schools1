<?php

use App\Models\CommunicationTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Force-replaces the WhatsApp body for the 6 Meta-approved system triggers
 * with the canonical Apr 2026 content.
 *
 * The earlier 2026_04_29_140000 migration only updated rows whose content
 * still matched the previous seeded default. Tenants that had been seeded
 * with custom content (older "Hi ##NAME##" / "Dear Parent, this is a reminder"
 * variants from per-school seeders) kept that text and therefore did not
 * receive the Meta-approved bodies.
 *
 * The user has confirmed that the canonical bodies below are what every
 * school should run, so this migration overwrites unconditionally for any
 * `is_system=true` WhatsApp row matching one of the six slugs. No tenant
 * customisation is preserved — restore from backup if rollback is needed.
 */
return new class extends Migration
{
    /**
     * Canonical content + variable list per slug. Embedded inline (instead of
     * pulling from CommunicationTemplate::SYSTEM_TRIGGERS) so the migration is
     * reproducible even if the constant is edited later.
     */
    private const CANONICAL = [
        'fee_payment_confirmed' => [
            'content'   => "Dear Parent,\n\nWe have received Rs.##AMOUNT## towards school fee for ##NAME## on ##DATETIME## via ##PAYMENT_METHOD##. Thank you for the prompt payment.\n\n- ##SCHOOL_NAME## Administration",
            'variables' => ['name', 'amount', 'receipt_no', 'datetime', 'payment_method', 'course_name', 'batch_name', 'school_name'],
        ],
        'fee_due_reminder' => [
            'content'   => "Dear Parent,\n\nThis is a gentle reminder that school fee of Rs.##AMOUNT## is due for ##NAME## (##COURSE_NAME##) by ##DATE##. Please make the payment at the earliest to avoid late fee charges.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['name', 'amount', 'date', 'course_name', 'batch_name', 'school_name'],
        ],
        'attendance_update' => [
            'content'   => "Dear Parent,\n\nWe would like to inform you that ##NAME## was marked ##ATTENDANCE## on ##DATE## for ##COURSE_NAME## - ##BATCH_NAME##. Kindly contact the class teacher for any clarifications regarding attendance.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['name', 'attendance', 'date', 'father_name', 'course_name', 'batch_name', 'school_name', 'app_name'],
        ],
        'transport_attendance' => [
            'content'   => "Dear Parent,\n\nThis is to inform you that your ward ##NAME## has ##TRIP_LABEL## at ##STOP_NAME## on ##DATE## at ##TIME##. Please ensure pick-up and drop-off arrangements are in place.\n\nRegards,\n##SCHOOL_NAME## Transport Team",
            'variables' => ['name', 'status', 'trip_type', 'trip_label', 'stop_name', 'date', 'time', 'father_name', 'school_name', 'app_name'],
        ],
        'exam_published' => [
            'content'   => "Dear Parent,\n\nThe ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the school portal or contact the class teacher for further details and timetable.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['name', 'title', 'datetime', 'class_name', 'type', 'school_name'],
        ],
        'daily_report' => [
            'content'   => "Dear Admin,\n\nThe Daily Master Report for ##DATE## is ready. ##CAPTION## Please open the full PDF report at ##LINK## for detailed insights.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['app_name', 'date', 'caption', 'link', 'school_name'],
        ],
    ];

    public function up(): void
    {
        foreach (self::CANONICAL as $slug => $cfg) {
            CommunicationTemplate::query()
                ->where('type', 'whatsapp')
                ->where('slug', $slug)
                ->where('is_system', true)
                ->each(function (CommunicationTemplate $row) use ($cfg) {
                    $row->content   = $cfg['content'];
                    $row->variables = $cfg['variables'];
                    $row->save();
                });
        }
    }

    public function down(): void
    {
        // No-op: pre-update content varied per tenant. Restore from backup
        // if you need to revert.
    }
};
