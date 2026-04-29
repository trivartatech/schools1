<?php

namespace App\Http\Controllers\School\Settings;

use App\Http\Controllers\Controller;
use App\Models\ReceiptPrintSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReceiptPrintSettingsController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');
        $settings = ReceiptPrintSetting::forSchool($schoolId);

        return Inertia::render('School/Settings/ReceiptPrint', [
            'settings' => [
                'paper_size' => $settings->paper_size ?: 'A4',
                'copies'     => (int) ($settings->copies ?: 1),
            ],
            'paper_sizes' => ReceiptPrintSetting::PAPER_SIZES,
            'max_copies'  => ReceiptPrintSetting::MAX_COPIES,
            'copy_labels' => ReceiptPrintSetting::COPY_LABELS,
        ]);
    }

    public function update(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'paper_size' => ['required', Rule::in(ReceiptPrintSetting::PAPER_SIZES)],
            'copies'     => ['required', 'integer', 'min:1', 'max:' . ReceiptPrintSetting::MAX_COPIES],
        ]);

        ReceiptPrintSetting::forSchool($schoolId)->update($validated);

        return back()->with('success', 'Receipt print settings updated.');
    }
}
