<?php

namespace App\Http\Controllers\School\Stationary;

use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\StationaryFeePayment;
use App\Models\StationaryStudentAllocation;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StationaryFeeCollectionController extends Controller
{
    /**
     * GET /school/stationary/fees
     * List every allocation with its collection status.
     */
    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $allocations = StationaryStudentAllocation::tenant()
            ->with([
                'student:id,admission_no,first_name,last_name,user_id',
                'student.user:id,name',
                'student.currentAcademicHistory:id,student_id,academic_year_id,class_id,section_id',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'lineItems',
            ])
            ->when($request->filled('search'), function ($q) use ($request) {
                $needle = '%' . $request->search . '%';
                $q->whereHas('student', function ($s) use ($needle) {
                    $s->where('first_name', 'like', $needle)
                      ->orWhere('last_name', 'like', $needle)
                      ->orWhere('admission_no', 'like', $needle);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('payment_status', $request->status))
            ->when($request->filled('class_id'), function ($q) use ($request, $academicYearId) {
                $q->whereHas('student.currentAcademicHistory', function ($h) use ($request, $academicYearId) {
                    $h->where('class_id', $request->class_id);
                    if ($request->filled('section_id')) {
                        $h->where('section_id', $request->section_id);
                    }
                    if ($academicYearId) {
                        $h->where('academic_year_id', $academicYearId);
                    }
                });
            })
            ->orderBy('payment_status')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get(['id', 'name']);

        $summary = [
            'total_due'     => (float) StationaryStudentAllocation::tenant()->sum('balance'),
            'total_paid'    => (float) StationaryStudentAllocation::tenant()->sum('amount_paid'),
            'unpaid_count'  => StationaryStudentAllocation::tenant()->where('payment_status', 'unpaid')->count(),
            'partial_count' => StationaryStudentAllocation::tenant()->where('payment_status', 'partial')->count(),
            'paid_count'    => StationaryStudentAllocation::tenant()->where('payment_status', 'paid')->count(),
        ];

        return Inertia::render('School/Stationary/Fees/Index', [
            'allocations' => $allocations,
            'classes'     => $classes,
            'filters'     => $request->only(['search', 'status', 'class_id', 'section_id']),
            'summary'     => $summary,
        ]);
    }

    /**
     * GET /school/stationary/fees/{allocation}
     * Single-allocation collection screen + receipt history.
     */
    public function show(StationaryStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);

        $allocation->load([
            'student:id,admission_no,first_name,last_name,user_id',
            'student.user:id,name',
            'lineItems.item:id,name,code,unit_price',
            'payments' => fn ($q) => $q->orderByDesc('payment_date')->orderByDesc('id'),
            'payments.collectedBy:id,name',
        ]);

        return Inertia::render('School/Stationary/Fees/Collect', [
            'allocation'   => $allocation,
            'paymentModes' => collect(PaymentMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ])->values(),
        ]);
    }

    /**
     * POST /school/stationary/fees/{allocation}/collect
     */
    public function store(Request $request, StationaryStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);

        $validated = $request->validate([
            'amount_paid'     => 'required|numeric|min:0.01',
            'discount'        => 'nullable|numeric|min:0',
            'fine'            => 'nullable|numeric|min:0',
            'payment_date'    => 'required|date',
            'payment_mode'    => 'required|string',
            'transaction_ref' => 'nullable|string|max:100',
            'remarks'         => 'nullable|string|max:500',
        ]);

        $discount = (float) ($validated['discount'] ?? 0);
        $fine     = (float) ($validated['fine']     ?? 0);

        if ((float) $validated['amount_paid'] + $discount > (float) $allocation->balance + $fine + 0.01) {
            return back()->withErrors([
                'amount_paid' => 'Payment (' . number_format($validated['amount_paid'], 2) . ') + discount ('
                                . number_format($discount, 2) . ') exceeds outstanding balance ('
                                . number_format($allocation->balance, 2) . ').',
            ]);
        }

        $academicYearId = app()->bound('current_academic_year_id')
            ? app('current_academic_year_id')
            : AcademicYear::where('school_id', $allocation->school_id)->where('is_current', true)->value('id');

        $payment = null;
        DB::transaction(function () use (&$payment, $allocation, $validated, $discount, $fine, $academicYearId) {
            $payment = StationaryFeePayment::create([
                'school_id'        => $allocation->school_id,
                'allocation_id'    => $allocation->id,
                'student_id'       => $allocation->student_id,
                'academic_year_id' => $academicYearId,
                'amount_paid'      => $validated['amount_paid'],
                'discount'         => $discount,
                'fine'             => $fine,
                'payment_date'     => $validated['payment_date'],
                'payment_mode'     => $validated['payment_mode'],
                'transaction_ref'  => $validated['transaction_ref'] ?? null,
                'remarks'          => $validated['remarks'] ?? null,
                'collected_by'     => auth()->id(),
            ]);

            $allocation->refresh()->recalculateTotals();
        });

        if ($payment && app()->bound('current_school')) {
            try {
                $payment->loadMissing(['student.studentParent', 'student.user', 'student.currentAcademicHistory.courseClass', 'student.currentAcademicHistory.section']);
                (new NotificationService(app('current_school')))->notifyFeePayment($payment);
            } catch (\Throwable $e) {
                Log::warning('Stationary fee payment notification failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Stationary fee payment recorded.');
    }

    /**
     * GET /school/stationary/fees/receipts/{payment}/receipt — PDF download.
     */
    public function receipt(StationaryFeePayment $payment)
    {
        abort_unless($payment->school_id === app('current_school_id'), 403);

        $payment->load([
            'allocation.lineItems.item:id,name,code',
            'student:id,admission_no,first_name,last_name',
            'collectedBy:id,name',
            'academicYear:id,name',
        ]);
        $school = \App\Models\School::find($payment->school_id);

        $verificationUrl = url("/verify-stationary-receipt/{$payment->receipt_no}");
        $qrCode = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(150)->generate($verificationUrl)
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.stationary-fee-receipt', [
            'payment' => $payment,
            'school'  => $school,
            'qrCode'  => $qrCode,
            'url'     => $verificationUrl,
        ]);

        return $pdf->stream("Stationary-Receipt-{$payment->receipt_no}.pdf");
    }

    /**
     * DELETE /school/stationary/fees/receipts/{payment} — void receipt.
     */
    public function destroy(StationaryFeePayment $payment)
    {
        abort_unless($payment->school_id === app('current_school_id'), 403);

        $allocationId = $payment->allocation_id;

        DB::transaction(function () use ($payment, $allocationId) {
            $payment->delete();
            StationaryStudentAllocation::find($allocationId)?->recalculateTotals();
        });

        return back()->with('success', 'Receipt voided.');
    }

    private function authorizeTenant(StationaryStudentAllocation $allocation): void
    {
        abort_unless($allocation->school_id === app('current_school_id'), 403);
    }
}
