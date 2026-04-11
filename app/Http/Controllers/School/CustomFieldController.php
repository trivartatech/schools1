<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\CustomField;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school')->id;
        
        // Allowed entity types to filter by (for the tabs in the UI)
        $entityTypes = ['student', 'staff', 'guardian'];
        
        // Default to 'student' if no valid entity_type is provided
        $entityType = $request->query('entity_type', 'student');
        if (!in_array($entityType, $entityTypes)) {
            $entityType = 'student';
        }

        $fields = CustomField::where('school_id', $schoolId)
                    ->where('entity_type', $entityType)
                    ->ordered()
                    ->get();

        return Inertia::render('School/Settings/CustomFields', [
            'fields'      => $fields,
            'entityType'  => $entityType,
            'entityTypes' => $entityTypes,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school')->id;

        $validated = $request->validate([
            'entity_type'  => 'required|string|in:student,staff,guardian',
            'label'        => 'required|string|max:200',
            'type'         => 'required|string|in:text,textarea,number,date,select,checkbox,radio',
            'options'      => 'nullable|string', // We receive it as a comma separated string from UI
            'is_required'  => 'boolean',
            'is_active'    => 'boolean',
        ]);

        // Auto-generate a safe snake_case name for the database column equivalent
        $baseName = Str::snake($validated['label']);
        $name = $baseName;
        $counter = 1;

        // Ensure name uniqueness per entity type in this school
        while (CustomField::where('school_id', $schoolId)
                ->where('entity_type', $validated['entity_type'])
                ->where('name', $name)
                ->exists()) {
            $name = $baseName . '_' . $counter;
            $counter++;
        }

        $validated['school_id'] = $schoolId;
        $validated['name']      = $name;
        
        // Convert comma-separated string to JSON array for select/radio
        if (in_array($validated['type'], ['select', 'radio']) && !empty($validated['options'])) {
            $optionsArray = array_map('trim', explode(',', $validated['options']));
            $validated['options'] = array_filter($optionsArray);
        } else {
            $validated['options'] = null; // Clear if not applicable
        }

        $validated['is_required'] = $validated['is_required'] ?? false;
        $validated['is_active']   = $validated['is_active'] ?? true;

        CustomField::create($validated);

        return redirect()->back()->with('status', 'Custom field created successfully.');
    }

    public function update(Request $request, CustomField $customField)
    {
        if ($customField->school_id !== app('current_school')->id) {
            abort(403);
        }

        $validated = $request->validate([
            'label'        => 'required|string|max:200',
            'options'      => 'nullable|string',
            'is_required'  => 'boolean',
            'is_active'    => 'boolean',
        ]);

        // Convert comma-separated string to array
        if (in_array($customField->type, ['select', 'radio']) && !empty($validated['options'])) {
            $optionsArray = array_map('trim', explode(',', $validated['options']));
            $validated['options'] = array_filter($optionsArray);
        } else {
            $validated['options'] = $customField->options; // Keep existing or null if changing types isn't allowed (it's not)
        }

        $customField->update($validated);

        return redirect()->back()->with('status', 'Custom field updated successfully.');
    }

    public function destroy(CustomField $customField)
    {
        if ($customField->school_id !== app('current_school')->id) {
            abort(403);
        }

        $customField->delete();

        return redirect()->back()->with('status', 'Custom field deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $schoolId = app('current_school')->id;

        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*.id'    => 'required|integer|exists:custom_fields,id',
            'order.*.order' => 'required|integer',
        ]);

        foreach ($validated['order'] as $item) {
            CustomField::where('id', $item['id'])
                ->where('school_id', $schoolId)
                ->update(['sort_order' => $item['order']]);
        }

        return response()->json(['success' => true]);
    }
}
