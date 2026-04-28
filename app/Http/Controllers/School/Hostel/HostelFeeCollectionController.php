<?php

namespace App\Http\Controllers\School\Hostel;

use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\HostelFeePayment;
use App\Models\HostelStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HostelFeeCollectionController extends Controller
{
    /**
     * GET /school/hostel/fees
     * List every allocation with its collection status.
     */
    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $allocations = HostelStudent::tenant()
            ->with([
                'student:id,admission_no,first_name,last_name',
                'student.user:id,name',
                'student.currentAcademicHistory:id,student_id,academic_year_id,class_id,section_id',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'bed:id,name,hostel_room_id,status',
                'bed.room:id,room_number,hostel_id,cost_per_month',
                'bed.room.hostel:id,name',
            ])
            ->when($request->filled('search'), function ($q) use ($request) {
                $needle = '%' . $request->search . '%';
                $q->whereHas('student', function ($s) use ($needle) {
                    $s->where('first_name', 'like', $needle)
                      ->orWhere('last_name', 'like', $needle)
                      ->orWhere('admission_no', 'like', $needle);
                });
            })
            ->when($request->filled('status'),    fn($q) => $q->where('payment_status', $request->status))
            ->when($request->filled('hostel_id'), function ($q) use ($request) {
                $q->whereHas('bed.room', fn($r) => $r->where('hostel_id', $request->hostel_id));
            })
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
            ->orderBy('payment_status') // unpaid first
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $hostels = \App\Models\Hostel::where('school_id', $schoolId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get(['id', 'name']);

        $summary = [
            'total_due'     => (float) HostelStudent::tenant()->sum('balance'),
            'total_paid'    => (float) HostelStudent::tenant()->sum('amount_paid'),
            'unpaid_count'  => HostelStudent::tenant()->where('payment_status', 'unpaid')->count(),
            'partial_count' => HostelStudent::tenant()->where('payment_status', 'partial')->count(),
            'paid_count'    => HostelStudent::tenant()->where('payment_status', 'paid')->count(),
        ];

        return Inertia::render('School/Hostel/Fees/Index', [
            'allocations' => $allocations,
            'hostels'     => $hostels,
            'classes'     => $classes,
            'filters'     => $request->only(['search', 'status', 'hostel_id', 'class_id', 'section_id']),
            'summary'     => $summary,
        ]);
    }

    /**
     * GET /school/hostel/fees/{allocation}
     * Show the collection screen for a single allocation + its receipts history.
     */
    public function show(HostelStudent $allocation)
    {
        $this->authorizeTenant($allocation);

        $allocation->load([
            'student:id,admission_no,first_name,last_name',
            'student.user:id,name',
            'bed:id,name,hostel_room_id,status',
            'bed.room:id,room_number,hostel_id,cost_per_month',
            'bed.room.hostel:id,name',
            'payments' => fn($q) => $q->orderBy('payment_date', 'desc')->orderBy('id', 'desc'),
            'payments.collectedBy:id,name',
        ]);

        return Inertia::render('School/Hostel/Fees/Collect', [
            'allocation'   => $allocation,
            'paymentModes' => collect(PaymentMode::cases())->map(fn($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ])->values(),
        ]);
    }

    /**
     * POST /school/hostel/fees/{allocation}/collect
     * Record a single payment receipt against the allocation.
     */
    public function store(Request $request, HostelStudent $allocation)
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

        // Prevent overpayment
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

        DB::transaction(function () use ($allocation, $validated, $discount, $fine, $academicYearId) {
            HostelFeePayment::create([
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

        return back()->with('success', 'Hostel fee payment recorded.');
    }

    /**
     * GET /school/hostel/fees/receipts/{payment}/receipt
     * Stream the printable PDF receipt.
     */
    public function receipt(HostelFeePayment $payment)
    {
        abort_unless($payment->school_id === app('current_school_id'), 403);

        $payment->load([
            'allocation.bed:id,name,hostel_room_id',
            'allocation.bed.room:id,room_number,hostel_id',
            'allocation.bed.room.hostel:id,name',
            'student:id,admission_no,first_name,last_name',
            'collectedBy:id,name',
            'academicYear:id,name',
        ]);
        $school = \App\Models\School::find($payment->school_id);

        $verificationUrl = url("/verify-hostel-receipt/{$payment->receipt_no}");
        $qrCode = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(150)->generate($verificationUrl)
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hostel-fee-receipt', [
            'payment' => $payment,
            'school'  => $school,
            'qrCode'  => $qrCode,
            'url'     => $verificationUrl,
        ]);

        return $pdf->stream("Hostel-Receipt-{$payment->receipt_no}.pdf");
    }

    /**
     * DELETE /school/hostel/fees/receipts/{payment}
     * Void a receipt (soft-delete). Recomputes the allocation totals.
     */
    public function destroy(HostelFeePayment $payment)
    {
        abort_unless($payment->school_id === app('current_school_id'), 403);

        $allocationId = $payment->allocation_id;

        DB::transaction(function () use ($payment, $allocationId) {
            $payment->delete();
            HostelStudent::find($allocationId)?->recalculateTotals();
        });

        return back()->with('success', 'Receipt voided.');
    }

    private function authorizeTenant(HostelStudent $allocation): void
    {
        abort_unless($allocation->school_id === app('current_school_id'), 403);
    }
}
