<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $schoolId = app('current_school_id');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('fee_groups')->where('school_id', $schoolId),
            ],
            'description' => 'nullable|string|max:500',
        ];
    }
}
