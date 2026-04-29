<?php

use App\Models\CommunicationTemplate;
use App\Models\School;
use Illuminate\Database\Migrations\Migration;

/**
 * Replace seeded WhatsApp content for 6 system triggers with the
 * Meta-approved Utility templates (Apr 2026).
 *
 * Meta enforces:
 *   - Variable density: ≥ ~20 chars of fixed text per variable
 *   - No variable at start or end of body
 *   - Templates must match what is sent literally (else delivery fails)
 *
 * The new bodies all close with `##SCHOOL_NAME## Administration` (or
 * `Transport Team`) so a single approved template can serve every tenant.
 *
 * Existing custom content is preserved — only rows whose content still
 * matches the previous seeded default are upgraded. Admin-edited rows
 * remain untouched.
 *
 * For new schools, CommunicationTemplate::SYSTEM_TRIGGERS already has the
 * new defaults, so seedSystemTemplatesForSchool() will insert them
 * directly going forward.
 */
return new class extends Migration
{
    /**
     * slug => [old_content, new_content, new_variables_array]
     *
     * The `old_content` snapshots are exactly what was seeded prior to this
     * migration — used as a guard so we don't clobber admin edits.
     */
    private const UPGRADES = [
        'fee_payment_confirmed' => [
            'old' => 'Dear Parent, Rs.##AMOUNT## fee received for ##NAME## (Receipt: ##RECEIPT_NO##) on ##DATETIME## via ##PAYMENT_METHOD##.',
            'new' => "Dear Parent,\n\nWe have received Rs.##AMOUNT## towards school fee for ##NAME## on ##DATETIME## via ##PAYMENT_METHOD##. Thank you for the prompt payment.\n\n- ##SCHOOL_NAME## Administration",
            'variables' => ['name', 'amount', 'receipt_no', 'datetime', 'payment_method', 'course_name', 'batch_name', 'school_name'],
        ],
        'fee_due_reminder' => [
            'old' => 'Dear Parent, fee of Rs.##AMOUNT## is due for ##NAME## (##COURSE_NAME##) by ##DATE##. Please pay at the earliest.',
            'new' => "Dear Parent,\n\nThis is a gentle reminder that school fee of Rs.##AMOUNT## is due for ##NAME## (##COURSE_NAME##) by ##DATE##. Please make the payment at the earliest to avoid late fee charges.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['name', 'amount', 'date', 'course_name', 'batch_name', 'school_name'],
        ],
        'attendance_update' => [
            'old' => 'Dear ##FATHER_NAME##, your ward ##NAME## was marked ##ATTENDANCE## on ##DATE## at ##COURSE_NAME## - ##BATCH_NAME##.',
            'new' => "Dear Parent,\n\nWe would like to inform you that ##NAME## was marked ##ATTENDANCE## on ##DATE## for ##COURSE_NAME## - ##BATCH_NAME##. Kindly contact the class teacher for any clarifications regarding attendance.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['name', 'attendance', 'date', 'father_name', 'course_name', 'batch_name', 'school_name', 'app_name'],
        ],
        'transport_attendance' => [
            'old' => 'Dear ##FATHER_NAME##, your ward ##NAME## has ##TRIP_LABEL## at ##STOP_NAME## on ##DATE## at ##TIME##.',
            'new' => "Dear Parent,\n\nThis is to inform you that your ward ##NAME## has ##TRIP_LABEL## at ##STOP_NAME## on ##DATE## at ##TIME##. Please ensure pick-up and drop-off arrangements are in place.\n\nRegards,\n##SCHOOL_NAME## Transport Team",
            'variables' => ['name', 'status', 'trip_type', 'trip_label', 'stop_name', 'date', 'time', 'father_name', 'school_name', 'app_name'],
        ],
        'exam_published' => [
            'old' => 'Dear Parent, ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the portal for details.',
            'new' => "Dear Parent,\n\nThe ##TITLE## exam schedule for ##CLASS_NAME## has been published. Please check the school portal or contact the class teacher for further details and timetable.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['name', 'title', 'datetime', 'class_name', 'type', 'school_name'],
        ],
        'daily_report' => [
            'old' => '##APP_NAME## — Daily Master Report (##DATE##)' . "\n\n" . '##CAPTION##' . "\n\n" . 'Open full PDF: ##LINK##',
            'new' => "Dear Admin,\n\nThe Daily Master Report for ##DATE## is ready. ##CAPTION## Please open the full PDF report at ##LINK## for detailed insights.\n\nRegards,\n##SCHOOL_NAME## Administration",
            'variables' => ['app_name', 'date', 'caption', 'link', 'school_name'],
        ],
    ];

    public function up(): void
    {
        // Make sure every school has every system row (idempotent — inserts only the missing).
        // New schools created after this migration will receive the new defaults
        // straight from SYSTEM_TRIGGERS. This call is here for any school that
        // was created between the previous seed migration and this one.
        School::query()->each(function ($school) {
            CommunicationTemplate::seedSystemTemplatesForSchool($school->id);
        });

        foreach (self::UPGRADES as $slug => $config) {
            CommunicationTemplate::query()
                ->where('type', 'whatsapp')
                ->where('slug', $slug)
                ->where('is_system', true)
                ->where('content', $config['old'])
                ->each(function (CommunicationTemplate $row) use ($config) {
                    $row->content   = $config['new'];
                    $row->variables = $config['variables'];
                    $row->save();
                });
        }
    }

    public function down(): void
    {
        foreach (self::UPGRADES as $slug => $config) {
            CommunicationTemplate::query()
                ->where('type', 'whatsapp')
                ->where('slug', $slug)
                ->where('is_system', true)
                ->where('content', $config['new'])
                ->each(function (CommunicationTemplate $row) use ($config) {
                    $row->content = $config['old'];
                    $row->save();
                });
        }
    }
};
