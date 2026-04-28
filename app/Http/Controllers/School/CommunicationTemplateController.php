<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CommunicationTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommunicationTemplateController extends Controller
{
    public function index($type)
    {
        $schoolId = app('current_school_id');

        CommunicationTemplate::seedSystemTemplatesForSchool($schoolId);

        $templates = CommunicationTemplate::where('school_id', $schoolId)
            ->where('type', $type)
            ->orderByRaw('is_system DESC, name ASC')
            ->get();

        $allVariables = collect(CommunicationTemplate::SYSTEM_TRIGGERS)
            ->flatMap(fn ($cfg) => $cfg['variables'])
            ->unique()
            ->map(fn ($v) => '##'.strtoupper($v).'##')
            ->sort()
            ->values()
            ->all();

        $triggers = collect(CommunicationTemplate::SYSTEM_TRIGGERS)
            ->map(fn ($cfg, $slug) => [
                'value'     => $slug,
                'label'     => $cfg['name'],
                'system'    => true,
                'channels'  => $cfg['channels'],
                'variables' => array_map(fn ($v) => '##'.strtoupper($v).'##', $cfg['variables']),
            ])
            ->values()
            ->push([
                'value'     => 'custom',
                'label'     => 'Custom / Manual',
                'system'    => false,
                'channels'  => ['sms', 'whatsapp', 'voice', 'push'],
                'variables' => $allVariables,
            ])
            ->all();

        return Inertia::render('School/Communication/Templates/Index', [
            'templates'    => $templates,
            'type'         => $type,
            'triggers'     => $triggers,
            'allVariables' => $allVariables,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'          => 'required|string',
            'name'          => 'required|string',
            'slug'          => 'required|string',
            'template_id'   => 'nullable|string',
            'subject'       => 'nullable|string',
            'content'       => 'nullable|string',
            'audio_url'     => 'nullable|string',
            'language_code' => 'nullable|string',
            'variables'     => 'nullable|array',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['school_id'] = app('current_school_id');

        CommunicationTemplate::create($validated);

        return back()->with('success', 'Template created successfully.');
    }

    public function update(Request $request, CommunicationTemplate $template)
    {
        if ($template->school_id !== app('current_school_id')) abort(403);

        $rules = [
            'name'          => 'required|string',
            'template_id'   => 'nullable|string',
            'subject'       => 'nullable|string',
            'content'       => 'nullable|string',
            'audio_url'     => 'nullable|string',
            'language_code' => 'nullable|string',
            'variables'     => 'nullable|array',
            'is_active'     => 'nullable|boolean',
        ];

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

        if ($template->is_system || array_key_exists($template->slug, CommunicationTemplate::SYSTEM_TRIGGERS)) {
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
