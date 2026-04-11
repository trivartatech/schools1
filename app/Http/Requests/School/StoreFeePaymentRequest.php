<?php

namespace App\Http\Requests\School;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $schoolId = app('current_school_id');

        return [
            'student_id'      => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'fee_head_id'     => ['required', Rule::exists('fee_heads', 'id')->where('school_id', $schoolId)],
            'academic_year_id'=> ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'amount_paid'     => 'required|numeric|min:0.01',
            'amount_due'      => 'required|numeric|min:0',
            'payment_mode'    => ['required', Rule::enum(PaymentMode::class)],
            'payment_date'    => 'required|date|before_or_equal:today',
            'term'            => 'nullable|string|max:50',
            'transaction_ref' => 'nullable|string|max:255',
            'remarks'         => 'nullable|string|max:500',
            'discount'        => 'nullable|numeric|min:0',
            'fine'            => 'nullable|numeric|min:0',
            'concession_id'   => ['nullable', Rule::exists('fee_concessions', 'id')],
            'concession_note' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required'       => 'Student is required.',
            'fee_head_id.required'      => 'Fee head is required.',
            'academic_year_id.required' => 'Academic year is required.',
            'amount_paid.required'      => 'Payment amount is required.',
            'amount_paid.min'           => 'Payment amount must be greater than zero.',
            'payment_mode.required'     => 'Payment mode is required.',
            'payment_date.required'     => 'Payment date is required.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
        ];
    }
}
