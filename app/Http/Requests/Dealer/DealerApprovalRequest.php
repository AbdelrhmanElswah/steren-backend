<?php

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealerApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'reason' => 'nullable|string|max:1000',
            'reviewed_by' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'The action field is required.',
            'action.in' => 'The action must be either approve or reject.',
            'reason.max' => 'The reason may not be greater than 1000 characters.',
            'reviewed_by.max' => 'The reviewed by field may not be greater than 255 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'action' => 'action',
            'reason' => 'reason',
            'reviewed_by' => 'reviewed by',
        ];
    }
}
