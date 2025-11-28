<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'partner_id' => 'sometimes|exists:partners,id',
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'birth_date' => 'sometimes|date|date_format:Y-m-d|before:today',
            'profile_img' => 'sometimes|nullable|string|url|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'partner_id.string' => 'Partner id must be a string',
            'full_name.string' => 'Full name must be a string',
            'email.string' => 'Email must be a string',
            'email.email' => 'Email must be a valid email address',
            'phone.string' => 'Phone must be a string',
            'birth_date.date' => 'Birth date must be a valid date',
            'birth_date.date_format' => 'Birth date must be in YYYY-MM-DD format',
            'birth_date.before' => 'Birth date must be before today',
            'profile_img.url' => 'Profile image must be a valid URL',
            'profile_img.max' => 'Profile image URL cannot exceed 2048 characters',
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
            'birth_date' => 'birth date',
            'profile_img' => 'profile image',
        ];
    }
}