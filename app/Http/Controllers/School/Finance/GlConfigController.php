<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\Ledger;
use App\Models\LedgerType;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Manages the GL (General Ledger) account mapping used for auto-posting:
 *   - Fee payments  → Dr Cash/Bank,   Cr Fee Income
 *   - Expenses      → Dr Expense,     Cr Cash/Bank
 *   - Payroll       → Dr Payroll Exp, Cr Cash/Bank
 *
 * Settings are stored in the school's JSON `settings` column.
 */
class GlConfigController extends Controller
{
    private const KEYS = [
        'gl_cash_ledger_id',
        'gl_fee_income_ledger_id',
        'gl_transport_fee_income_ledger_id',
        'gl_hostel_fee_income_ledger_id',
        'gl_stationary_fee_income_ledger_id',
        'gl_expense_ledger_id',
        'gl_payroll_ledger_id',
    ];

    public function show()
    {
        $schoolId = app('current_school_id');
        $school   = School::find($schoolId);
        $settings = $school?->settings ?? [];

        // Auto-create essential ledger types and ledgers if they don't exist
        $this->ensureDefaultLedgers($schoolId, $school);
        // Reload settings after potential auto-config
        $settings = $school->fresh()->settings ?? [];

        $ledgers = Ledger::where('school_id', $schoolId)
            ->where('is_active', true)
            ->with('ledgerType')
            ->orderBy('name')
            ->get();

        $mapped = [];
        foreach (self::KEYS as $key) {
            $mapped[$key] = $settings[$key] ?? null;
        }

        // Expense categories with their current per-category ledger mapping
        $categories = ExpenseCategory::where('school_id', $schoolId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'ledger_id']);

        return Inertia::render('School/Finance/GlConfig', [
            'ledgers'    => $ledgers,
            'settings'   => $mapped,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request)
    {
        $schoolId = app('current_school_id');

        $data = $request->validate([
            'gl_cash_ledger_id'                  => 'nullable|integer',
            'gl_fee_income_ledger_id'            => 'nullable|integer',
            'gl_transport_fee_income_ledger_id'  => 'nullable|integer',
            'gl_hostel_fee_income_ledger_id'     => 'nullable|integer',
            'gl_stationary_fee_income_ledger_id' => 'nullable|integer',
            'gl_expense_ledger_id'               => 'nullable|integer',
            'gl_payroll_ledger_id'               => 'nullable|integer',
        ]);

        // Verify each provided ledger belongs to this school
        foreach (array_filter($data) as $ledgerId) {
            abort_unless(
                Ledger::where('id', $ledgerId)->where('school_id', $schoolId)->exists(),
                403, 'Ledger does not belong to this school.'
            );
        }

        $school   = School::find($schoolId);
        $settings = $school->settings ?? [];

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key]) {
                    $settings[$key] = (int) $data[$key];
                } else {
                    unset($settings[$key]);
                }
            }
        }

        $school->update(['settings' => $settings]);

        return back()->with('success', 'GL account mapping saved.');
    }

    /**
     * Auto-create essential ledger types and ledger accounts if they don't exist.
     * Also auto-configures GL settings for unmapped accounts.
     */
    private function ensureDefaultLedgers(int $schoolId, School $school): void
    {
        // Ensure ledger types exist
        $assetType = LedgerType::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Asset'],
            ['nature' => 'debit', 'is_system' => true]
        );
        $incomeType = LedgerType::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Income'],
            ['nature' => 'credit', 'is_system' => true]
        );
        $expenseType = LedgerType::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Expense'],
            ['nature' => 'debit', 'is_system' => true]
        );

        // Ensure Cash/Bank ledger
        $cashLedger = Ledger::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Cash / Bank'],
            ['ledger_type_id' => $assetType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'debit']
        );

        // Ensure Fee Income ledger
        $feeIncomeLedger = Ledger::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Fee Income'],
            ['ledger_type_id' => $incomeType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'credit']
        );

        // Ensure Transport Fee Income ledger (separated so transport revenue
        // shows on its own row in the P&L; falls back to Fee Income if unmapped)
        $transportIncomeLedger = Ledger::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Transport Fee Income'],
            ['ledger_type_id' => $incomeType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'credit']
        );

        // Ensure Hostel Fee Income ledger (same treatment as Transport)
        $hostelIncomeLedger = Ledger::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Hostel Fee Income'],
            ['ledger_type_id' => $incomeType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'credit']
        );

        // Ensure Stationary Fee Income ledger (same treatment as Transport / Hostel)
        $stationaryIncomeLedger = Ledger::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'Stationary Fee Income'],
            ['ledger_type_id' => $incomeType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'credit']
        );

        // Ensure Expense ledger
        $expenseLedger = Ledger::firstOrCreate(
            ['school_id' => $schoolId, 'name' => 'General Expenses'],
            ['ledger_type_id' => $expenseType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'debit']
        );

        // Auto-configure GL settings for unmapped or mis-mapped accounts
        $settings = $school->settings ?? [];
        $changed = false;

        // Fix cash ledger: must be an Asset-type ledger (not Income/Expense)
        $currentCash = $settings['gl_cash_ledger_id'] ?? null;
        if (! $currentCash || ! Ledger::where('id', $currentCash)->where('school_id', $schoolId)->where('ledger_type_id', $assetType->id)->exists()) {
            $settings['gl_cash_ledger_id'] = $cashLedger->id;
            $changed = true;
        }

        // Fix fee income ledger: must be an Income-type ledger
        $currentIncome = $settings['gl_fee_income_ledger_id'] ?? null;
        if (! $currentIncome || ! Ledger::where('id', $currentIncome)->where('school_id', $schoolId)->where('ledger_type_id', $incomeType->id)->exists()) {
            $settings['gl_fee_income_ledger_id'] = $feeIncomeLedger->id;
            $changed = true;
        }

        // Fix transport fee income ledger: must be an Income-type ledger
        $currentTransport = $settings['gl_transport_fee_income_ledger_id'] ?? null;
        if (! $currentTransport || ! Ledger::where('id', $currentTransport)->where('school_id', $schoolId)->where('ledger_type_id', $incomeType->id)->exists()) {
            $settings['gl_transport_fee_income_ledger_id'] = $transportIncomeLedger->id;
            $changed = true;
        }

        // Fix hostel fee income ledger: must be an Income-type ledger
        $currentHostel = $settings['gl_hostel_fee_income_ledger_id'] ?? null;
        if (! $currentHostel || ! Ledger::where('id', $currentHostel)->where('school_id', $schoolId)->where('ledger_type_id', $incomeType->id)->exists()) {
            $settings['gl_hostel_fee_income_ledger_id'] = $hostelIncomeLedger->id;
            $changed = true;
        }

        // Fix stationary fee income ledger: must be an Income-type ledger
        $currentStationary = $settings['gl_stationary_fee_income_ledger_id'] ?? null;
        if (! $currentStationary || ! Ledger::where('id', $currentStationary)->where('school_id', $schoolId)->where('ledger_type_id', $incomeType->id)->exists()) {
            $settings['gl_stationary_fee_income_ledger_id'] = $stationaryIncomeLedger->id;
            $changed = true;
        }

        // Fix expense ledger: must be an Expense-type ledger
        $currentExpense = $settings['gl_expense_ledger_id'] ?? null;
        if (! $currentExpense || ! Ledger::where('id', $currentExpense)->where('school_id', $schoolId)->where('ledger_type_id', $expenseType->id)->exists()) {
            $settings['gl_expense_ledger_id'] = $expenseLedger->id;
            $changed = true;
        }

        if ($changed) {
            $school->update(['settings' => $settings]);
        }
    }

    // ── Per-category ledger mapping ───────────────────────────

    public function updateCategoryMapping(Request $request)
    {
        $schoolId = app('current_school_id');

        $request->validate([
            'mappings'              => 'required|array',
            'mappings.*.id'         => 'required|integer',
            'mappings.*.ledger_id'  => 'nullable|integer',
        ]);

        foreach ($request->mappings as $row) {
            $category = ExpenseCategory::where('id', $row['id'])
                ->where('school_id', $schoolId)
                ->first();

            if (! $category) continue;

            $ledgerId = $row['ledger_id'] ? (int) $row['ledger_id'] : null;

            // Verify ledger belongs to this school
            if ($ledgerId) {
                abort_unless(
                    Ledger::where('id', $ledgerId)->where('school_id', $schoolId)->exists(),
                    403, 'Ledger does not belong to this school.'
                );
            }

            $category->update(['ledger_id' => $ledgerId]);
        }

        return back()->with('success', 'Expense category ledger mapping saved.');
    }
}
