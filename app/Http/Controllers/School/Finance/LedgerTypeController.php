<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\LedgerType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LedgerTypeController extends Controller
{
    // ── Default types seeded on first access ──────────────────
    private const DEFAULTS = [
        ['name' => 'Asset',     'nature' => 'debit',  'description' => 'Resources owned by the school (cash, bank, property)'],
        ['name' => 'Liability', 'nature' => 'credit', 'description' => 'Amounts owed by the school (loans, payables)'],
        ['name' => 'Capital',   'nature' => 'credit', 'description' => 'School funds, reserves and surplus'],
        ['name' => 'Income',    'nature' => 'credit', 'description' => 'Revenue and earnings (fees, grants, interest)'],
        ['name' => 'Expense',   'nature' => 'debit',  'description' => 'Costs and expenditures of the school'],
    ];

    public function index()
    {
        $schoolId = app('current_school_id');

        // FIX #11: use firstOrCreate (not exists+create) to prevent race-condition
        // duplicate inserts when two requests hit this page simultaneously for the
        // first time. The unique index on (school_id, name) is the final safety net.
        foreach (self::DEFAULTS as $d) {
            LedgerType::firstOrCreate(
                ['school_id' => $schoolId, 'name' => $d['name']],
                array_merge($d, ['school_id' => $schoolId, 'is_system' => true])
            );
        }

        $types = LedgerType::where('school_id', $schoolId)
            ->withCount('ledgers')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Finance/LedgerTypes/Index', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'nature'      => 'required|in:debit,credit',
            'description' => 'nullable|string|max:500',
        ]);

        // Unique per school
        if (LedgerType::where('school_id', $schoolId)->where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'A ledger type with this name already exists.']);
        }

        LedgerType::create(array_merge($data, ['school_id' => $schoolId, 'is_system' => false]));

        return back()->with('success', 'Ledger type created.');
    }

    public function update(Request $request, LedgerType $ledgerType)
    {
        $this->authorize($ledgerType);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'nature'      => 'required|in:debit,credit',
            'description' => 'nullable|string|max:500',
        ]);

        // Unique check (exclude self)
        $exists = LedgerType::where('school_id', $ledgerType->school_id)
            ->where('name', $data['name'])
            ->where('id', '!=', $ledgerType->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['name' => 'A ledger type with this name already exists.']);
        }

        $ledgerType->update($data);

        return back()->with('success', 'Ledger type updated.');
    }

    public function destroy(LedgerType $ledgerType)
    {
        $this->authorize($ledgerType);

        if ($ledgerType->is_system) {
            return back()->withErrors(['error' => 'System ledger types cannot be deleted.']);
        }

        if ($ledgerType->ledgers()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete — ledgers exist under this type.']);
        }

        $ledgerType->delete();

        return back()->with('success', 'Ledger type deleted.');
    }

    // ── Tenant guard ─────────────────────────────────────────
    private function authorize(LedgerType $type): void
    {
        abort_unless($type->school_id === app('current_school_id'), 403);
    }
}
