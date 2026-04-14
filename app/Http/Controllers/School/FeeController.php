<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreFeeGroupRequest;
use App\Http\Requests\School\UpdateFeeGroupRequest;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\FeeGroup;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Services\GlPostingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FeeController extends Controller
{
    // ─────────────────────────── FEE GROUP CRUD ──────────────────────────

    public function groupsIndex()
    {
        $schoolId = app('current_school_id');
        $groups = FeeGroup::where('school_id', $schoolId)->with('feeHeads')->get();

        return Inertia::render('School/Fee/Groups', ['groups' => $groups]);
    }

    public function groupStore(StoreFeeGroupRequest $request)
    {
        $schoolId = app('current_school_id');
        FeeGroup::create(['school_id' => $schoolId, 'name' => $request->name, 'description' => $request->description]);
        return back()->with('success', 'Fee group created.');
    }

    public function groupUpdate(UpdateFeeGroupRequest $request, FeeGroup $feeGroup)
    {
        if ($feeGroup->school_id !== app('current_school_id')) abort(403);
        $feeGroup->update(['name' => $request->name, 'description' => $request->description]);
        return back()->with('success', 'Fee group updated.');
    }

    public function groupDestroy(FeeGroup $feeGroup)
    {
        if ($feeGroup->school_id !== app('current_school_id')) abort(403);
        $feeGroup->delete();
        return back()->with('success', 'Fee group deleted.');
    }

    // ─────────────────────────── FEE HEAD CRUD ───────────────────────────

    public function headStore(Request $request)
    {
        $schoolId = app('current_school_id');
        $request->validate([
            'fee_group_id'     => 'required|exists:fee_groups,id',
            'name'             => [
                'required', 'string', 'max:100',
                Rule::unique('fee_heads')->where('school_id', $schoolId)
            ],
            'short_code'       => [
                'nullable', 'string', 'max:10',
                Rule::unique('fee_heads')->where('school_id', $schoolId)
            ],
            'is_taxable'       => 'boolean',
            'gst_percent'      => 'numeric|min:0|max:28',
            'is_transport_fee' => 'boolean',
            'is_hostel_fee'    => 'boolean',
        ]);

        // Only one transport fee head per school
        if ($request->boolean('is_transport_fee')) {
            FeeHead::where('school_id', $schoolId)->update(['is_transport_fee' => false]);
        }

        // Only one hostel fee head per school
        if ($request->boolean('is_hostel_fee')) {
            FeeHead::where('school_id', $schoolId)->update(['is_hostel_fee' => false]);
        }

        FeeHead::create(array_merge($request->only(['fee_group_id', 'name', 'short_code', 'is_taxable', 'gst_percent', 'sort_order']), [
            'school_id'        => $schoolId,
            'is_transport_fee' => $request->boolean('is_transport_fee'),
            'is_hostel_fee'    => $request->boolean('is_hostel_fee'),
        ]));
        return back()->with('success', 'Fee head added.');
    }

    public function headUpdate(Request $request, FeeHead $feeHead)
    {
        $schoolId = app('current_school_id');
        if ($feeHead->school_id !== $schoolId) abort(403);

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('fee_heads')->where('school_id', $schoolId)->ignore($feeHead->id)
            ],
            'short_code' => [
                'nullable', 'string', 'max:10',
                Rule::unique('fee_heads')->where('school_id', $schoolId)->ignore($feeHead->id)
            ],
            'is_transport_fee' => 'boolean',
            'is_hostel_fee'    => 'boolean',
        ]);

        // Only one transport fee head per school
        if ($request->boolean('is_transport_fee')) {
            FeeHead::where('school_id', $schoolId)
                ->where('id', '!=', $feeHead->id)
                ->update(['is_transport_fee' => false]);
        }

        // Only one hostel fee head per school
        if ($request->boolean('is_hostel_fee')) {
            FeeHead::where('school_id', $schoolId)
                ->where('id', '!=', $feeHead->id)
                ->update(['is_hostel_fee' => false]);
        }

        $feeHead->update($request->only(['name', 'short_code', 'is_taxable', 'gst_percent', 'sort_order', 'is_transport_fee', 'is_hostel_fee']));
        return back()->with('success', 'Fee head updated.');
    }

    public function headDestroy(FeeHead $feeHead)
    {
        if ($feeHead->school_id !== app('current_school_id')) abort(403);
        $feeHead->delete();
        return back()->with('success', 'Fee head deleted.');
    }

    // ─────────────────────────── FEE STRUCTURE BUILDER ──────────────────

    public function structureIndex()
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $classes    = CourseClass::where('school_id', $schoolId)->orderBy('sort_order')->get();
        $feeHeads   = FeeHead::where('school_id', $schoolId)->with('feeGroup')->orderBy('sort_order')->get();
        $structures = FeeStructure::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['feeHead.feeGroup', 'courseClass'])
            ->get();

        return Inertia::render('School/Fee/Structure', [
            'classes'    => $classes,
            'feeHeads'   => $feeHeads,
            'structures' => $structures,
        ]);
    }

    public function structureStore(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $request->validate([
            'class_id'         => 'required|exists:course_classes,id',
            'fee_head_id'      => 'required|exists:fee_heads,id',
            'term'             => 'required|string|max:50',
            'amount'           => 'required|numeric|min:0',
            'late_fee_per_day' => 'nullable|numeric|min:0',
            'due_date'         => 'nullable|date',
            'is_optional'      => 'boolean',
            'student_type'     => 'in:all,new,old',
            'gender'           => 'in:all,male,female',
        ]);

        FeeStructure::updateOrCreate(
            [
                'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'class_id' => $request->class_id, 
                'fee_head_id' => $request->fee_head_id, 'term' => $request->term
            ],
            [
                'amount' => $request->amount, 'late_fee_per_day' => $request->late_fee_per_day ?? 0, 'due_date' => $request->due_date,
                'is_optional' => $request->is_optional ?? false,
                'student_type' => $request->student_type ?? 'all',
                'gender' => $request->gender ?? 'all',
            ]
        );

        return back()->with('success', 'Fee structure saved.');
    }

    public function structureDestroy(FeeStructure $feeStructure)
    {
        if ($feeStructure->school_id !== app('current_school_id')) abort(403);
        $feeStructure->delete();
        return back()->with('success', 'Entry removed.');
    }

    // ─────────────────────────── FEE COLLECTION ──────────────────────────

    public function collectIndex(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $student    = null;
        $structures = [];
        $payments   = [];

        if ($request->filled('student_id')) {
            $student = Student::with(['currentAcademicHistory.courseClass'])->find($request->student_id);

            if ($student && $academicYearId) {
                $history   = $student->currentAcademicHistory;
                $classId   = $history?->class_id;

                // Determine if student is new or old
                // If they have > 1 history record, they are old, otherwise new.
                $historyCount = StudentAcademicHistory::where('student_id', $student->id)->count();
                $studentType = $historyCount > 1 ? 'old' : 'new';
                $gender = strtolower($student->gender);

                $structures = FeeStructure::where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('class_id', $classId)
                    ->whereIn('gender', ['all', $gender])
                    ->whereIn('student_type', ['all', $studentType])
                    ->with('feeHead.feeGroup')
                    ->get();

                $payments = FeePayment::where('student_id', $student->id)
                    ->where('academic_year_id', $academicYearId)
                    ->with(['feeHead', 'collectedBy:id,first_name,last_name,name', 'glTransaction:id,transaction_no'])
                    ->get();

                // ── Extract configured terms before stripping them ───────────────────
                // This allows the UI to display the exact "term" the admin configured
                // in the Fee Structure (e.g. 'Term 1', 'Annual', 'Monthly')
                $transportTerm = $structures->first(fn($s) => $s->feeHead?->is_transport_fee ?? false)?->term ?? 'annual';
                $hostelTerm    = $structures->first(fn($s) => $s->feeHead?->is_hostel_fee ?? false)?->term ?? 'annual';

                // ── Strip transport and hostel fee heads from class structures ────────
                // Transport and hostel amounts are per-student (from allocation), not per-class.
                // Remove any class-level ₹0 entries so they don't block the real amount.
                $structures = $structures->filter(fn($s) => !($s->feeHead?->is_transport_fee ?? false) && !($s->feeHead?->is_hostel_fee ?? false))->values();

                // ── Transport Fee Schedule from Allocation (source of truth) ─────────
                // Pull amount from allocation, and balance = amount - all paid receipts.
                // This way EVERY payment creates its own receipt in Payment History.
                $transportHead = \App\Models\FeeHead::where('school_id', $schoolId)
                    ->where('is_transport_fee', true)
                    ->first();

                $transportAllocation = \App\Models\TransportStudentAllocation::where('student_id', $student->id)
                    ->where('school_id', $schoolId)
                    ->where('status', 'active')
                    ->first();

                if ($transportHead && $transportAllocation && $transportAllocation->transport_fee > 0) {
                    $transportPayments = $payments
                        ->where('fee_head_id', $transportHead->id)
                        ->where('amount_paid', '>', 0);

                    $totalPaid     = $transportPayments->sum('amount_paid');
                    $totalDiscount = $transportPayments->sum('discount');
                    $totalFine     = $transportPayments->sum('fine');

                    $balance = max(0, (float) $transportAllocation->transport_fee - $totalPaid - $totalDiscount + $totalFine);
                    $status  = $balance <= 0 ? 'paid' : (($totalPaid + $totalDiscount) > 0 ? 'partial' : 'due');

                    // Always show — even when paid, so staff can see the completed entry
                    $structures = $structures->concat([[
                        'id'          => 'transport-alloc-' . $transportAllocation->id,
                        'fee_head_id' => $transportHead->id,
                        'fee_head'    => $transportHead->load('feeGroup'),
                        'term'        => $transportTerm,
                        'amount'      => $transportAllocation->transport_fee,
                        'is_optional' => false,
                        'source'      => 'payment',
                        'payment_id'  => null,
                        'balance'     => $balance,
                        'status'      => $status,
                    ]]);
                }
                // ────────────────────────────────────────────────────────────────────

                // ── Hostel Fee Schedule from Allocation (source of truth) ────────────
                $hostelHead = \App\Models\FeeHead::where('school_id', $schoolId)
                    ->where('is_hostel_fee', true)
                    ->first();

                $hostelAllocation = \App\Models\HostelStudent::with('bed.room')
                    ->where('student_id', $student->id)
                    ->whereNull('vacate_date')
                    ->where('status', 'Active')
                    ->first();

                if ($hostelHead && $hostelAllocation && $hostelAllocation->bed && $hostelAllocation->bed->room) {
                    $monthlyHostelFee = (float) $hostelAllocation->bed->room->cost_per_month;

                    if ($monthlyHostelFee > 0) {
                        $hostelPayments = $payments
                            ->where('fee_head_id', $hostelHead->id)
                            ->where('amount_paid', '>', 0);

                        $totalPaid     = $hostelPayments->sum('amount_paid');
                        $totalDiscount = $hostelPayments->sum('discount');
                        $totalFine     = $hostelPayments->sum('fine');

                        $balance = max(0, $monthlyHostelFee - $totalPaid - $totalDiscount + $totalFine);
                        $status  = $balance <= 0 ? 'paid' : (($totalPaid + $totalDiscount) > 0 ? 'partial' : 'due');

                        $structures = $structures->concat([[
                            'id'          => 'hostel-alloc-' . $hostelAllocation->id,
                            'fee_head_id' => $hostelHead->id,
                            'fee_head'    => $hostelHead->load('feeGroup'),
                            'term'        => $hostelTerm,
                            'amount'      => $monthlyHostelFee,
                            'is_optional' => false,
                            'source'      => 'payment',
                            'payment_id'  => null,
                            'balance'     => $balance,
                            'status'      => $status,
                        ]]);
                    }
                }
                // ────────────────────────────────────────────────────────────────────

                // ── Other ad-hoc due payments (non-transport, non-hostel) ────────────
                $existingHeadIds = $structures->pluck('fee_head_id')->unique();

                $adhocDue = $payments->filter(function ($p) use ($existingHeadIds) {
                    if ($p->feeHead?->is_transport_fee ?? false) return false; // handled above
                    if ($p->feeHead?->is_hostel_fee ?? false) return false;    // handled above
                    return in_array($p->status, ['due', 'partial'])
                        && !$existingHeadIds->contains($p->fee_head_id);
                });

                $adhocScheduleItems = $adhocDue->map(fn($p) => [
                    'id'          => 'pay-' . $p->id,
                    'fee_head_id' => $p->fee_head_id,
                    'fee_head'    => $p->feeHead,
                    'term'        => $p->term,
                    'amount'      => $p->amount_due,
                    'is_optional' => false,
                    'source'      => 'payment',
                    'payment_id'  => $p->id,
                    'balance'     => (float) $p->balance,
                    'status'      => $p->status,
                ])->values();

                // Merge everything: class structures + transport + hostel + other adhoc
                $structures = $structures
                    ->map(fn($s) => is_array($s) ? $s : array_merge($s->toArray(), ['source' => 'structure']))
                    ->concat($adhocScheduleItems);

                // ── Payment History: hide auto-created due records only (amount_paid=0) ─
                // Actual receipts (amount_paid > 0) always show in history.
                $payments = $payments->filter(function ($p) {
                    $isTransport = $p->feeHead?->is_transport_fee ?? false;
                    $isHostel    = $p->feeHead?->is_hostel_fee ?? false;
                    $isBlankDue  = $p->status === \App\Enums\FeePaymentStatus::Due && (float) $p->amount_paid === 0.0;
                    return !(($isTransport || $isHostel) && $isBlankDue);
                })->values();
                // ─────────────────────────────────────────────────────────────────────

            }

        }

        // Search & Filter
        $students = [];
        if ($request->filled('search') || $request->filled('class_id') || $request->filled('section_id')) {
            $query = Student::where('school_id', $schoolId);

            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('first_name', 'like', '%' . $request->search . '%')
                      ->orWhere('admission_no', 'like', '%' . $request->search . '%');
                });
            }

            if ($request->filled('class_id') || $request->filled('section_id')) {
                $query->whereHas('currentAcademicHistory', function ($q) use ($request, $academicYearId) {
                    $q->where('academic_year_id', $academicYearId);
                    if ($request->filled('class_id')) {
                        $q->where('class_id', $request->class_id);
                    }
                    if ($request->filled('section_id')) {
                        $q->where('section_id', $request->section_id);
                    }
                });
            }

            $students = $query->limit(20)->get(['id', 'first_name', 'last_name', 'admission_no', 'roll_no']);
        }

        $classes = CourseClass::where('school_id', $schoolId)->with('sections')->get();

        // All concessions for the selected student
        $concessions = [];
        if ($student) {
            $concessions = \App\Models\FeeConcession::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('student_id', $student->id)
                ->with('createdBy:id,first_name,last_name,name')
                ->withCount('payments')
                ->get();
        }

        return Inertia::render('School/Fee/Collect', [
            'student'     => $student,
            'structures'  => $structures,
            'payments'    => $payments,
            'students'    => $students,
            'classes'     => $classes,
            'concessions' => $concessions,
        ]);
    }

    public function collectStore(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $request->validate([
            // FIX #9: scope student and fee_head to current school
            'student_id'          => [
                'required',
                \Illuminate\Validation\Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'fee_head_id'         => [
                'required',
                \Illuminate\Validation\Rule::exists('fee_heads', 'id')->where('school_id', $schoolId),
            ],
            'term'                => 'required|string|max:50',
            'amount_due'          => 'required|numeric|min:0',
            'amount_paid'         => 'required|numeric|min:0',
            'discount'            => 'nullable|numeric|min:0',
            'fine'                => 'nullable|numeric|min:0',
            'payment_mode'        => 'required|in:cash,cheque,online,upi,dd,card',
            // FIX #10: disallow future payment dates
            'payment_date'        => 'required|date|before_or_equal:today',
            'transaction_ref'     => 'nullable|string|max:100',
            'remarks'             => 'nullable|string',
            'concession_id'       => 'nullable|exists:fee_concessions,id',
            'existing_payment_id' => 'nullable|exists:fee_payments,id',
        ]);

        $amountDue       = (float) $request->amount_due;
        $amountPaid      = (float) $request->amount_paid;
        $fine            = (float) ($request->fine ?? 0);
        $concessionId    = $request->concession_id ?? null;
        $concessionNote  = null;

        // Fetch the Fee Head to check for Tax properties
        $feeHead = \App\Models\FeeHead::find($request->fee_head_id);
        $taxableAmount = $amountPaid;
        $taxAmount     = 0.00;
        $taxPercent    = 0.00;

        if ($feeHead && $feeHead->is_taxable && $feeHead->gst_percent > 0) {
            $taxPercent    = (float) $feeHead->gst_percent;
            $taxableAmount = round($amountPaid / (1 + ($taxPercent / 100)), 2);
            $taxAmount     = round($amountPaid - $taxableAmount, 2);
        }

        if ($concessionId) {
            // Prevent double-dipping: A concession can only be applied ONCE per fee head & term
            $alreadyUsedQuery = \App\Models\FeePayment::where('student_id', $request->student_id)
                ->where('fee_head_id', $request->fee_head_id)
                ->where('term', $request->term)
                ->where('concession_id', $concessionId);

            if ($request->filled('existing_payment_id')) {
                $alreadyUsedQuery->where('id', '!=', $request->existing_payment_id);
            }

            if ($alreadyUsedQuery->exists()) {
                return back()->withErrors(['concession_id' => 'This specific concession has already been applied to this fee.']);
            }

            $concession = \App\Models\FeeConcession::find($concessionId);
            if ($concession) {
                $discount       = $concession->calculateDiscount($amountDue);
                $concessionNote = "{$concession->name}: {$concession->description}";
            } else {
                $discount = (float)($request->discount ?? 0);
            }
        } else {
            $discount = (float) ($request->discount ?? 0);
        }

        $balance = max(0, $amountDue - $discount + $fine - $amountPaid);
        $status  = $balance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'due');

        // ── If an existing due payment is being settled (e.g. transport fee) ──
        // UPDATE the existing record instead of creating a new one to prevent duplicates.
        if ($request->filled('existing_payment_id')) {
            $existingPayment = FeePayment::where('id', $request->existing_payment_id)
                ->where('school_id', $schoolId)
                ->firstOrFail();

            $existingPayment->update([
                'amount_due'      => $amountDue,
                'amount_paid'     => $amountPaid,
                'discount'        => $discount,
                'concession_id'   => $concessionId,
                'fine'            => $fine,
                'balance'         => $balance,
                'taxable_amount'  => $taxableAmount,
                'tax_amount'      => $taxAmount,
                'tax_percent'     => $taxPercent,
                'payment_mode'    => $request->payment_mode,
                'payment_date'    => $request->payment_date,
                'transaction_ref' => $request->transaction_ref,
                'status'          => $status,
                'remarks'         => $request->remarks,
                'collected_by'    => auth()->id(),
            ]);

            // Post to GL if not already posted (observer only fires on create, not update)
            if (!$existingPayment->gl_transaction_id && $amountPaid > 0) {
                app(GlPostingService::class)->postFeePayment($existingPayment->fresh());
            }

            (new \App\Services\NotificationService(app('current_school')))->notifyFeePayment($existingPayment);

            return back()->with('success', "Special fee payment (Transport/Hostel/Ad-hoc) recorded! Balance: ₹{$balance}");
        }
        // ─────────────────────────────────────────────────────────────────────



        $payment = FeePayment::create([
            'school_id'        => $schoolId,
            'student_id'       => $request->student_id,
            'academic_year_id' => $academicYearId,
            'fee_head_id'      => $request->fee_head_id,
            'term'             => $request->term,
            'amount_due'       => $amountDue,
            'amount_paid'      => $amountPaid,
            'discount'         => $discount,
            'fine'             => $fine,
            'balance'          => $balance,
            'taxable_amount'   => $taxableAmount,
            'tax_amount'       => $taxAmount,
            'tax_percent'      => $taxPercent,
            'payment_mode'     => $request->payment_mode,
            'payment_date'     => $request->payment_date,
            'transaction_ref'  => $request->transaction_ref,
            'status'           => $status,
            'remarks'          => $request->remarks,
            'collected_by'     => auth()->id(),
            'concession_id'    => $concessionId,
            'concession_note'  => $concessionNote,
        ]);

        // Trigger Notification
        (new NotificationService(app('current_school')))->notifyFeePayment($payment);

        $discountMsg = $discount > 0 ? " (Discount: ₹{$discount})" : '';
        return back()->with('success', "Payment recorded! Balance: ₹{$balance}{$discountMsg}");
    }

    public function collectUpdate(Request $request, FeePayment $feePayment)
    {
        $schoolId = app('current_school_id');
        if ($feePayment->school_id !== $schoolId) abort(403);

        $request->validate([
            'fee_head_id'    => 'required|exists:fee_heads,id',
            'term'           => 'required|string|max:50',
            'amount_due'     => 'required|numeric|min:0',
            'amount_paid'    => 'required|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0',
            'fine'           => 'nullable|numeric|min:0',
            'payment_mode'   => 'required|in:cash,cheque,online,upi,dd,card',
            'payment_date'   => 'required|date',
            'transaction_ref'=> 'nullable|string|max:100',
            'remarks'        => 'nullable|string',
            'receipt_no'     => [
                'nullable', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('fee_payments', 'receipt_no')
                    ->where('school_id', $schoolId)
                    ->ignore($feePayment->id),
            ],
        ]);

        $amountDue  = $request->amount_due;
        $amountPaid = $request->amount_paid;
        $discount   = $request->discount ?? 0;
        $fine       = $request->fine ?? 0;
        $balance    = max(0, $amountDue - $discount + $fine - $amountPaid);
        $status     = $balance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'due');

        // Fetch the Fee Head to check for Tax properties
        $feeHead = \App\Models\FeeHead::find($request->fee_head_id);
        $taxableAmount = $amountPaid; // Default assumes no tax
        $taxAmount     = 0.00;
        $taxPercent    = 0.00;

        // Item-wise Tax calculation
        if ($feeHead && $feeHead->is_taxable && $feeHead->gst_percent > 0) {
            $taxPercent = (float) $feeHead->gst_percent;
            // The amount_paid includes GST.
            // Base = Total / (1 + (GST/100))
            $taxableAmount = round($amountPaid / (1 + ($taxPercent / 100)), 2);
            $taxAmount     = round($amountPaid - $taxableAmount, 2);
        }

        $updatePayload = [
            'fee_head_id'     => $request->fee_head_id,
            'term'            => $request->term,
            'amount_due'      => $amountDue,
            'amount_paid'     => $amountPaid,
            'discount'        => $discount,
            'fine'            => $fine,
            'balance'         => $balance,
            'taxable_amount'  => $taxableAmount,
            'tax_amount'      => $taxAmount,
            'tax_percent'     => $taxPercent,
            'payment_mode'    => $request->payment_mode,
            'payment_date'    => $request->payment_date,
            'transaction_ref' => $request->transaction_ref,
            'status'          => $status,
            'remarks'         => $request->remarks,
        ];
        // Only overwrite receipt_no if explicitly provided
        if ($request->filled('receipt_no')) {
            $updatePayload['receipt_no'] = $request->receipt_no;
        }
        $feePayment->update($updatePayload);

        // Post to GL if not already posted (observer only fires on create)
        if (!$feePayment->gl_transaction_id && $amountPaid > 0) {
            app(GlPostingService::class)->postFeePayment($feePayment->fresh());
        }

        return back()->with('success', "Payment updated successfully.");
    }

    public function collectDestroy(FeePayment $feePayment)
    {
        $this->authorize('delete', $feePayment);
        $feePayment->delete();
        return back()->with('success', 'Payment record deleted.');
    }

    // ──────────────────── FEE RECEIPT CONFIG ─────────────────────────────

    public function configShow(Request $request)
    {
        $schoolId = app('current_school_id');
        $school   = \App\Models\School::findOrFail($schoolId);
        $settings = $school->settings ?? [];

        $activeYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        return Inertia::render('School/Fee/Config', [
            'feeConfig' => [
                'prefix'     => $settings['fee_receipt_prefix']    ?? 'FEE-',
                'suffix'     => $settings['fee_receipt_suffix']    ?? '',
                'start_no'   => $settings['fee_receipt_start_no']  ?? 1,
                'pad_length' => $settings['fee_receipt_pad_length'] ?? 5,
            ],
            'currentCount'      => FeePayment::where('school_id', $schoolId)->count(),
            'academicYearName'  => $activeYear?->name ?? '??-??',
        ]);
    }

    public function configUpdate(Request $request)
    {
        $schoolId = app('current_school_id');
        $school   = \App\Models\School::findOrFail($schoolId);

        $validated = $request->validate([
            'prefix'     => 'nullable|string|max:20',
            'suffix'     => 'nullable|string|max:20',
            'start_no'   => 'required|integer|min:1',
            'pad_length' => 'required|integer|min:1|max:10',
        ]);

        $settings = $school->settings ?? [];
        $settings['fee_receipt_prefix']     = $validated['prefix']     ?? '';
        $settings['fee_receipt_suffix']     = $validated['suffix']     ?? '';
        $settings['fee_receipt_start_no']   = $validated['start_no'];
        $settings['fee_receipt_pad_length'] = $validated['pad_length'];

        $school->update(['settings' => $settings]);

        return back()->with('success', 'Fee receipt format saved successfully!');
    }

    /**
     * PATCH /school/fee/collect/{feePayment}/receipt-no
     * Update just the receipt number for a specific fee payment.
     */
    public function updateReceiptNo(Request $request, FeePayment $feePayment)
    {
        $schoolId = app('current_school_id');

        $request->validate([
            'receipt_no' => [
                'required', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('fee_payments', 'receipt_no')
                    ->where('school_id', $schoolId)
                    ->ignore($feePayment->id),
            ],
        ]);

        $feePayment->update(['receipt_no' => $request->receipt_no]);

        return back()->with('success', 'Receipt number updated successfully.');
    }

    /**
     * POST /school/fee/collect/{feePayment}/post-gl
     * Manually post a fee payment to the General Ledger
     */
    public function collectPostGl(FeePayment $feePayment)
    {
        abort_if($feePayment->school_id !== app('current_school_id'), 403);

        $tx = app(\App\Services\GlPostingService::class)->postFeePayment($feePayment);

        if ($tx) {
            return back()->with('success', 'Posted to GL: ' . $tx->transaction_no);
        }

        return back()->with('info', $feePayment->gl_transaction_id
            ? 'Already posted to GL.'
            : 'GL not configured for this school.');
    }

    /**
     * POST /school/fee/collect/batch-post-gl
     * Batch-post all unlinked fee payments to the General Ledger
     */
    public function batchPostGl()
    {
        $schoolId = app('current_school_id');
        $glService = app(GlPostingService::class);

        $unposted = FeePayment::where('school_id', $schoolId)
            ->whereNull('gl_transaction_id')
            ->where('amount_paid', '>', 0)
            ->get();

        $posted = 0;
        foreach ($unposted as $payment) {
            $tx = $glService->postFeePayment($payment);
            if ($tx) $posted++;
        }

        if ($posted === 0) {
            return back()->with('info', $unposted->isEmpty()
                ? 'All fee payments are already synced to GL.'
                : 'GL is not configured. Go to Finance → GL Config to set up Cash and Fee Income ledger accounts.');
        }

        return back()->with('success', "{$posted} fee payment(s) posted to General Ledger.");
    }

    /**
     * GET /school/fee/collect/{feePayment}/receipt
     * Generate PDF receipt with QR code
     */
    public function receipt(FeePayment $feePayment)
    {
        $schoolId = app('current_school_id');
        abort_if($feePayment->school_id !== $schoolId, 403);

        $feePayment->load(['student', 'feeHead.feeGroup', 'collectedBy', 'academicYear']);
        $school = \App\Models\School::find($schoolId);

        // Prepare verification URL for the QR Code
        $verificationUrl = url("/verify-receipt/{$feePayment->receipt_no}");
        
        // Generate QR code base64
        $qrCode = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(150)->generate($verificationUrl));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.fee-receipt', [
            'payment' => $feePayment,
            'school'  => $school,
            'qrCode'  => $qrCode,
            'url'     => $verificationUrl,
        ]);

        return $pdf->stream("Receipt-{$feePayment->receipt_no}.pdf");
    }
}
