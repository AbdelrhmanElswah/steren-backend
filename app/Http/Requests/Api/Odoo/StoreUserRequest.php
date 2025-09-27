<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:users,odoo_id',
            'partner_id' => 'required|exists:partners,id',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'partner_id.required' => 'Partner id is required',
            'full_name.required' => 'Full name is required',
            'email.required' => 'Email is required',
            'phone.required' => 'Phone is required',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'partner_id' => 'partner id',
            'full_name' => 'full name',
            'email' => 'email',
            'phone' => 'phone',
        ];
    }
}