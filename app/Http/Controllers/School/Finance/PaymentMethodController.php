<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');

        $methods = PaymentMethod::where('school_id', $schoolId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return Inertia::render('School/Finance/PaymentMethods/Index', [
            'methods' => $methods,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('payment_methods')->where('school_id', $schoolId),
            ],
            'label'      => 'required|string|max:100',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'code.regex' => 'Code may only contain lowercase letters, digits, and underscores.',
        ]);

        PaymentMethod::create([
            'school_id'  => $schoolId,
            'code'       => $validated['code'],
            'label'      => $validated['label'],
            'is_active'  => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Payment method added.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        abort_unless($paymentMethod->school_id === app('current_school_id'), 403);

        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('payment_methods')
                    ->where('school_id', $paymentMethod->school_id)
                    ->ignore($paymentMethod->id),
            ],
            'label'      => 'required|string|max:100',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $paymentMethod->update([
            'code'       => $validated['code'],
            'label'      => $validated['label'],
            'is_active'  => $validated['is_active'] ?? $paymentMethod->is_active,
            'sort_order' => $validated['sort_order'] ?? $paymentMethod->sort_order,
        ]);

        return back()->with('success', 'Payment method updated.');
    }

    public function toggleActive(PaymentMethod $paymentMethod)
    {
        abort_unless($paymentMethod->school_id === app('current_school_id'), 403);

        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return back()->with('success', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        abort_unless($paymentMethod->school_id === app('current_school_id'), 403);

        $paymentMethod->delete();

        return back()->with('success', 'Payment method deleted.');
    }
}
