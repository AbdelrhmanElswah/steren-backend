<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:1000', Rule::requiredIf($this->input('action') === 'reject')],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'The action field is required (approve or reject).',
            'action.in' => 'The action must be either "approve" or "reject".',
            'reason.required_if' => 'A rejection reason is required when rejecting a listing.',
            'reason.max' => 'The rejection reason must not exceed 1000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'action' => 'action',
            'reason' => 'rejection reason',
        ];
    }
}

