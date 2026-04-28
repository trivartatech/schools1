<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CommunicationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CommunicationTemplateController extends Controller
{
    /**
     * Master list of all system triggers and the channels they support.
     * Each trigger is auto-seeded as a permanent (non-deletable) template.
     */
    private const SYSTEM_TRIGGERS = [
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

    public function index($type)
    {
        $schoolId = app('current_school_id');

        // Auto-seed permanent system templates for this channel type
        foreach (self::SYSTEM_TRIGGERS as $slug => $config) {
            if (!in_array($type, $config['channels'])) continue;

            CommunicationTemplate::firstOrCreate(
                ['school_id' => $schoolId, 'type' => $type, 'slug' => $slug],
                [
                    'name'      => $config['name'],
                    'is_system' => true,
                    'is_active' => true,
                    'variables' => $config['variables'],
                    'content'   => $config['defaults'][$type] ?? "Default {$config['name']} message.",
                    'subject'   => $config['subjects'][$type] ?? null,
                ]
            );
        }

        // Backfill is_system on rows that exist with a system slug but were
        // created before the flag was being set (or were imported / migrated
        // from another deployment). Without this, the UI still shows a Delete
        // button for these legacy rows.
        CommunicationTemplate::where('school_id', $schoolId)
            ->whereIn('slug', array_keys(self::SYSTEM_TRIGGERS))
            ->where('is_system', false)
            ->update(['is_system' => true]);

        $templates = CommunicationTemplate::where('school_id', $schoolId)
            ->where('type', $type)
            ->orderByRaw("is_system DESC, name ASC")
            ->get();

        return Inertia::render('School/Communication/Templates/Index', [
            'templates' => $templates,
            'type' => $type
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'name' => 'required|string',
            'slug' => 'required|string', // The trigger slug
            'template_id' => 'nullable|string',
            'subject' => 'nullable|string',
            'content' => 'nullable|string',
            'audio_url' => 'nullable|string',
            'language_code' => 'nullable|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['school_id'] = app('current_school_id');

        CommunicationTemplate::create($validated);

        return back()->with('success', 'Template created successfully.');
    }

    public function update(Request $request, CommunicationTemplate $template)
    {
        if ($template->school_id !== app('current_school_id')) abort(403);

        $rules = [
            'name' => 'required|string',
            'template_id' => 'nullable|string',
            'subject' => 'nullable|string',
            'content' => 'nullable|string',
            'audio_url' => 'nullable|string',
            'language_code' => 'nullable|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean'
        ];

        // Only allow slug change for non-system templates
        if (!$template->is_system) {
            $rules['slug'] = 'required|string';
        }

        $validated = $request->validate($rules);

        $template->update($validated);

        return back()->with('success', 'Template updated successfully.');
    }

    public function destroy(CommunicationTemplate $template)
    {
        if ($template->school_id !== app('current_school_id')) abort(403);

        // Belt-and-braces: block deletion if the row carries the system flag
        // OR if its slug is one of the predefined triggers. The slug check
        // catches legacy rows that exist without is_system=true set.
        if ($template->is_system || array_key_exists($template->slug, self::SYSTEM_TRIGGERS)) {
            return back()->with('error', 'System templates cannot be deleted.');
        }

        $template->delete();
        return back()->with('success', 'Template deleted successfully.');
    }

    public function toggle(CommunicationTemplate $template)
    {
        if ($template->school_id !== app('current_school_id')) abort(403);
        $template->update(['is_active' => !$template->is_active]);
        return back()->with('success', 'Template status updated.');
    }
}
