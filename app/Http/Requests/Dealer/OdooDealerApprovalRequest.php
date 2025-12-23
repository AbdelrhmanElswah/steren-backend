<?php

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OdooDealerApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * For Odoo integration, we don't require authentication
     */
    public function authorize(): bool
    {
        return true; // Allow Odoo integration without authentication
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:1000'], // Optional reason for rejection
            // reviewed_by is optional for Odoo integration
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'The action field is required.',
            'action.in' => 'The action must be either approve or reject.',
            'reason.max' => 'The reason may not be greater than 1000 characters.',
            'reviewed_by.max' => 'The reviewed by field may not be greater than 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'action' => 'action',
            'reason' => 'reason',
            'reviewed_by' => 'reviewed by',
        ];
    }
}