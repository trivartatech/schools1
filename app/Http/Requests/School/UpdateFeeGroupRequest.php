<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $schoolId  = app('current_school_id');
        $param      = $this->route('feeGroup') ?? $this->route('fee_group');
        $feeGroupId = is_object($param) ? $param->id : $param;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('fee_groups')->where('school_id', $schoolId)->ignore($feeGroupId),
            ],
            'description' => 'nullable|string|max:500',
        ];
    }
}
