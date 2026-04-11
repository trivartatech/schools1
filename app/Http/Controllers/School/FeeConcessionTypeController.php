<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\FeeConcessionType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeeConcessionTypeController extends Controller
{
    private function schoolId(): int
    {
        return app('current_school_id');
    }

    public function index()
    {
        $types = FeeConcessionType::where('school_id', $this->schoolId())
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Fee/ConcessionTypes/Index', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        $validated['school_id'] = $this->schoolId();

        FeeConcessionType::create($validated);

        return back()->with('success', 'Concession type added.');
    }

    public function update(Request $request, FeeConcessionType $concession_type)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        $concession_type->update($validated);

        return back()->with('success', 'Concession type updated.');
    }

    public function toggleActive(FeeConcessionType $concession_type)
    {
        $concession_type->update(['is_active' => !$concession_type->is_active]);
        return back()->with('success', $concession_type->is_active ? 'Activated.' : 'Deactivated.');
    }

    public function destroy(FeeConcessionType $concession_type)
    {
        $concession_type->delete();
        return back()->with('success', 'Concession type deleted.');
    }
}

