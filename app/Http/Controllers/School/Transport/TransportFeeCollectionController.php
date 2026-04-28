<?php

namespace App\Http\Controllers\School\Transport;

use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\TransportFeePayment;
use App\Models\TransportStudentAllocation;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TransportFeeCollectionController extends Controller
{
    /**
     * GET /school/transport/fees
     * List every allocation with its collection status.
     */
    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $allocations = TransportStudentAllocation::tenant()
            ->with([
                'student:id,admission_no,first_name,last_name',
                'student.user:id,name',
                'student.currentAcademicHistory:id,student_id,academic_year_id,class_id,section_id',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'route:id,route_name,route_code',
                'stop:id,stop_name,fee',
            ])
            ->when($request->filled('search'), function ($q) use ($request) {
                $needle = '%' . $request->search . '%';
                $q->whereHas('student', function ($s) use ($needle) {
                    $s->where('first_name', 'like', $needle)
                      ->orWhere('last_name', 'like', $needle)
                      ->orWhere('admission_no', 'like', $needle);
                });
            })
            ->when($request->filled('status'),   fn($q) => $q->where('payment_status', $request->status))
            ->when($request->filled('route_id'), fn($q) => $q->where('route_id', $request->route_id))
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

        $routes = \App\Models\TransportRoute::tenant()
            ->where('status', 'active')
            ->orderBy('route_name')
            ->get(['id', 'route_name', 'route_code']);

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get(['id', 'name']);

        $summary = [
            'total_due'     => (float) TransportStudentAllocation::tenant()->sum('balance'),
            'total_paid'    => (float) TransportStudentAllocation::tenant()->sum('amount_paid'),
            'unpaid_count'  => TransportStudentAllocation::tenant()->where('payment_status', 'unpaid')->count(),
            'partial_count' => TransportStudentAllocation::tenant()->where('payment_status', 'partial')->count(),
            'paid_count'    => TransportStudentAllocation::tenant()->where('payment_status', 'paid')->count(),
        ];

        return Inertia::render('School/Transport/Fees/Index', [
            'allocations' => $allocations,
            'routes'      => $routes,
            'classes'     => $classes,
            'filters'     => $request->only(['search', 'status', 'route_id', 'class_id', 'section_id']),
            'summary'     => $summary,
        ]);
    }

    /**
     * GET /school/transport/fees/{allocation}
     * Show the collection screen for a single allocation + its receipts history.
     */
    public function show(TransportStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);

        $allocation->load([
            'student:id,admission_no,first_name,last_name',
            'student.user:id,name',
            'route:id,route_name,route_code',
            'stop:id,stop_name,fee',
            'vehicle:id,vehicle_number,vehicle_name',
            'payments' => fn($q) => $q->orderBy('payment_date', 'desc')->orderBy('id', 'desc'),
            'payments.collectedBy:id,name',
        ]);

        return Inertia::render('School/Transport/Fees/Collect', [
            'allocation'   => $allocation,
            'paymentModes' => collect(PaymentMode::cases())->map(fn($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ])->values(),
        ]);
    }

    /**
     * POST /school/transport/fees/{allocation}/collect
     * Record a single payment receipt against the allocation.
     */
    public function store(Request $request, TransportStudentAllocation $allocation)
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
        $newBalance = max(0, (float) $allocation->balance + $fine - $discount - (float) $validated['amount_paid']);
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
            $payment = TransportFeePayment::create([
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

        // Notify parent (SMS / WhatsApp / push). Don't fail the request if it errors.
        if ($payment && app()->bound('current_school')) {
            try {
                $payment->loadMissing(['student.studentParent', 'student.user', 'student.currentAcademicHistory.courseClass', 'student.currentAcademicHistory.section']);
                (new NotificationService(app('current_school')))->notifyFeePayment($payment);
            } catch (\Throwable $e) {
                Log::warning('Transport fee payment notification failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Transport fee payment recorded.');
    }

    /**
     * GET /school/transport/fees/receipts/{payment}
     * Stream the printable PDF receipt.
     */
    public function receipt(TransportFeePayment $payment)
    {
        abort_unless($payment->school_id === app('current_school_id'), 403);

        $payment->load([
            'allocation.route:id,route_name,route_code',
            'allocation.stop:id,stop_name',
            'student:id,admission_no,first_name,last_name',
            'collectedBy:id,name',
            'academicYear:id,name',
        ]);
        $school = \App\Models\School::find($payment->school_id);

        $verificationUrl = url("/verify-transport-receipt/{$payment->receipt_no}");
        $qrCode = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(150)->generate($verificationUrl)
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.transport-fee-receipt', [
            'payment' => $payment,
            'school'  => $school,
            'qrCode'  => $qrCode,
            'url'     => $verificationUrl,
        ]);

        return $pdf->stream("Transport-Receipt-{$payment->receipt_no}.pdf");
    }

    /**
     * DELETE /school/transport/fees/receipts/{payment}
     * Void a receipt (soft-delete). Recomputes the allocation totals.
     */
    public function destroy(TransportFeePayment $payment)
    {
        abort_unless($payment->school_id === app('current_school_id'), 403);

        $allocationId = $payment->allocation_id;

        DB::transaction(function () use ($payment, $allocationId) {
            $payment->delete();
            TransportStudentAllocation::find($allocationId)?->recalculateTotals();
        });

        return back()->with('success', 'Receipt voided.');
    }

    private function authorizeTenant(TransportStudentAllocation $allocation): void
    {
        abort_unless($allocation->school_id === app('current_school_id'), 403);
    }
}
