<?php

namespace App\Http\Controllers\School\Hostel;

use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\HostelFeePayment;
use App\Models\HostelStudent;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            // Class + section for the student summary panel
            'student.currentAcademicHistory.courseClass:id,name',
            'student.currentAcademicHistory.section:id,name',
            'bed:id,name,hostel_room_id,status',
            'bed.room:id,room_number,hostel_id,cost_per_month',
            'bed.room.hostel:id,name',
            'payments' => fn($q) => $q->orderBy('payment_date', 'desc')->orderBy('id', 'desc'),
            'payments.collectedBy:id,name',
        ]);

        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $concessions = \App\Models\FeeConcession::where('school_id', $allocation->school_id)
            ->where('student_id', $allocation->student_id)
            ->where('fee_type', 'hostel')
            ->where('is_active', true)
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->where(fn ($q) => $q->where('is_one_time', false)->orWhereDoesntHave('hostelPayments'))
            ->get(['id', 'name', 'description', 'type', 'value', 'is_one_time']);

        return Inertia::render('School/Hostel/Fees/Collect', [
            'allocation'   => $allocation,
            'paymentModes' => \App\Models\PaymentMethod::where('school_id', $allocation->school_id)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('label')
                ->get(['code', 'label'])
                ->map(fn ($m) => ['value' => $m->code, 'label' => $m->label])
                ->values(),
            'concessions'  => $concessions,
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
            'concession_id'   => [
                'nullable',
                \Illuminate\Validation\Rule::exists('fee_concessions', 'id')
                    ->where('school_id', $allocation->school_id)
                    ->where('student_id', $allocation->student_id)
                    ->where('fee_type', 'hostel')
                    ->where('is_active', true),
            ],
            'fine'            => 'nullable|numeric|min:0',
            'payment_date'    => 'required|date|before_or_equal:today',
            'payment_mode'    => [
                'required', 'string',
                \Illuminate\Validation\Rule::exists('payment_methods', 'code')
                    ->where('school_id', $allocation->school_id)
                    ->where('is_active', true),
            ],
            'transaction_ref' => 'nullable|string|max:100',
            'remarks'         => 'nullable|string|max:500',
        ]);

        $fine         = (float) ($validated['fine'] ?? 0);
        $concessionId = $validated['concession_id'] ?? null;

        if ($concessionId) {
            $concession = \App\Models\FeeConcession::find($concessionId);
            if ($concession && $concession->is_one_time && \App\Models\HostelFeePayment::where('concession_id', $concessionId)->exists()) {
                return back()->withErrors(['concession_id' => 'This concession has already been applied.']);
            }
            $discount = $concession ? (float) $concession->calculateDiscount((float) $allocation->balance) : (float) ($validated['discount'] ?? 0);
        } else {
            $discount = (float) ($validated['discount'] ?? 0);
        }

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

        $payment = null;
        DB::transaction(function () use (&$payment, $allocation, $validated, $discount, $fine, $academicYearId, $concessionId) {
            $payment = HostelFeePayment::create([
                'school_id'        => $allocation->school_id,
                'allocation_id'    => $allocation->id,
                'student_id'       => $allocation->student_id,
                'academic_year_id' => $academicYearId,
                'amount_paid'      => $validated['amount_paid'],
                'discount'         => $discount,
                'concession_id'    => $concessionId,
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
                Log::warning('Hostel fee payment notification failed: ' . $e->getMessage());
            }
        }

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
            'student',
            'student.studentParent',
            'student.academicHistories.courseClass',
            'student.academicHistories.section',
            'collectedBy:id,name',
            'academicYear:id,name',
        ]);
        $school = \App\Models\School::find($payment->school_id);

        $verificationUrl = url("/verify-hostel-receipt/{$payment->receipt_no}");
        $qrCode = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(150)->generate($verificationUrl)
        );

        $printSettings = \App\Models\ReceiptPrintSetting::forSchool($payment->school_id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hostel-fee-receipt', [
            'payment'    => $payment,
            'school'     => $school,
            'qrCode'     => $qrCode,
            'url'        => $verificationUrl,
            'copyLabels' => $printSettings->copyLabels(),
        ])->setPaper(strtolower($printSettings->paper_size), 'portrait');

        $safeReceiptNo = str_replace(['/', '\\'], '-', (string) $payment->receipt_no);
        return $pdf->stream("Hostel-Receipt-{$safeReceiptNo}.pdf");
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

    /**
     * POST /school/hostel/fees/batch-post-gl
     * Sync every unposted hostel-fee receipt to the General Ledger. Safe to
     * retry — already-posted receipts are skipped, and rows where GL is not
     * yet configured silently no-op.
     */
    public function batchPostGl()
    {
        $schoolId  = app('current_school_id');
        $glService = app(\App\Services\GlPostingService::class);

        $unposted = HostelFeePayment::where('school_id', $schoolId)
            ->whereNull('gl_transaction_id')
            ->where('amount_paid', '>', 0)
            ->get();

        $posted = 0;
        foreach ($unposted as $payment) {
            $tx = $glService->postHostelFeePayment($payment);
            if ($tx) $posted++;
        }

        if ($posted === 0) {
            return back()->with('info', $unposted->isEmpty()
                ? 'All hostel-fee payments are already synced to GL.'
                : 'GL is not configured. Go to Finance → GL Config to set up Cash and Hostel Fee Income ledger accounts.');
        }

        return back()->with('success', "{$posted} hostel-fee payment(s) posted to General Ledger.");
    }
}
