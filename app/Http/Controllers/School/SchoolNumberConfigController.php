<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\TransferCertificate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchoolNumberConfigController extends Controller
{
    public function show()
    {
        $schoolId   = app('current_school_id');
        $school     = \App\Models\School::findOrFail($schoolId);
        $settings   = $school->settings ?? [];
        $activeYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        return Inertia::render('School/Settings/NumberFormats', [
            'admConfig' => [
                'prefix'     => $settings['adm_prefix']     ?? 'ADM',
                'suffix'     => $settings['adm_suffix']     ?? '',
                'start_no'   => $settings['adm_start_no']   ?? 1,
                'pad_length' => $settings['adm_pad_length']  ?? 4,
            ],
            'regConfig' => [
                'prefix'     => $settings['reg_prefix']     ?? 'REG-',
                'suffix'     => $settings['reg_suffix']     ?? '',
                'start_no'   => $settings['reg_start_no']   ?? 1,
                'pad_length' => $settings['reg_pad_length']  ?? 4,
            ],
            'feeConfig' => [
                'prefix'     => $settings['fee_receipt_prefix']    ?? 'FEE-',
                'suffix'     => $settings['fee_receipt_suffix']    ?? '',
                'start_no'   => $settings['fee_receipt_start_no']  ?? 1,
                'pad_length' => $settings['fee_receipt_pad_length'] ?? 5,
            ],
            'tcConfig' => [
                'prefix'     => $settings['tc_prefix']     ?? 'TC/',
                'suffix'     => $settings['tc_suffix']     ?? '/{YEAR}',
                'start_no'   => $settings['tc_start_no']   ?? 1,
                'pad_length' => $settings['tc_pad_length'] ?? 4,
            ],
            'admissionCount'    => Student::where('school_id', $schoolId)->count(),
            'registrationCount' => StudentApplication::where('school_id', $schoolId)->count(),
            'feeCount'          => FeePayment::where('school_id', $schoolId)->count(),
            'tcCount'           => TransferCertificate::where('school_id', $schoolId)->where('status', 'issued')->count(),
            'academicYearName'  => $activeYear?->name ?? '??-??',
        ]);
    }

    public function update(Request $request)
    {
        $schoolId = app('current_school_id');
        $school   = \App\Models\School::findOrFail($schoolId);

        $validated = $request->validate([
            // Admission
            'adm_prefix'     => 'nullable|string|max:20',
            'adm_suffix'     => 'nullable|string|max:20',
            'adm_start_no'   => 'required|integer|min:1',
            'adm_pad_length' => 'required|integer|min:1|max:10',
            // Registration
            'reg_prefix'     => 'nullable|string|max:20',
            'reg_suffix'     => 'nullable|string|max:20',
            'reg_start_no'   => 'required|integer|min:1',
            'reg_pad_length' => 'required|integer|min:1|max:10',
            // Fee Receipt
            'fee_prefix'     => 'nullable|string|max:20',
            'fee_suffix'     => 'nullable|string|max:20',
            'fee_start_no'   => 'required|integer|min:1',
            'fee_pad_length' => 'required|integer|min:1|max:10',
            // Transfer Certificate
            'tc_prefix'      => 'nullable|string|max:20',
            'tc_suffix'      => 'nullable|string|max:20',
            'tc_start_no'    => 'required|integer|min:1',
            'tc_pad_length'  => 'required|integer|min:1|max:10',
        ]);

        $settings = $school->settings ?? [];

        // Admission
        $settings['adm_prefix']     = $validated['adm_prefix']     ?? '';
        $settings['adm_suffix']     = $validated['adm_suffix']     ?? '';
        $settings['adm_start_no']   = $validated['adm_start_no'];
        $settings['adm_pad_length'] = $validated['adm_pad_length'];

        // Registration
        $settings['reg_prefix']     = $validated['reg_prefix']     ?? '';
        $settings['reg_suffix']     = $validated['reg_suffix']     ?? '';
        $settings['reg_start_no']   = $validated['reg_start_no'];
        $settings['reg_pad_length'] = $validated['reg_pad_length'];

        // Fee Receipt (use fee_receipt_* keys to match FeePayment model expectations)
        $settings['fee_receipt_prefix']     = $validated['fee_prefix']     ?? '';
        $settings['fee_receipt_suffix']     = $validated['fee_suffix']     ?? '';
        $settings['fee_receipt_start_no']   = $validated['fee_start_no'];
        $settings['fee_receipt_pad_length'] = $validated['fee_pad_length'];

        // Transfer Certificate
        $settings['tc_prefix']     = $validated['tc_prefix']     ?? '';
        $settings['tc_suffix']     = $validated['tc_suffix']     ?? '';
        $settings['tc_start_no']   = $validated['tc_start_no'];
        $settings['tc_pad_length'] = $validated['tc_pad_length'];

        $school->settings = $settings;
        $school->save();

        return back()->with('success', 'All number format settings saved successfully.');
    }
}
