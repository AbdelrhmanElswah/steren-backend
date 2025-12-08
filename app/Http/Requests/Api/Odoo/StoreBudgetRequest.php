<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'odoo_id' => 'required|string|unique:budgets,odoo_id',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'name_kr' => 'nullable|string|max:255',
            'value' => 'required|numeric|min:0',
            'sequence' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo ID is required',
            'odoo_id.unique' => 'Budget with this Odoo ID already exists',
            'name.required' => 'Budget name is required',
            'value.required' => 'Budget value is required',
            'value.numeric' => 'Budget value must be a number',
            'value.min' => 'Budget value must be at least 0',
            'sequence.required' => 'Sequence is required',
            'sequence.integer' => 'Sequence must be an integer',
        ];
    }
}

