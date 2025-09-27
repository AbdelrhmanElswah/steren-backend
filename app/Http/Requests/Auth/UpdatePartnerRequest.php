<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        
        return [
            'full_name' => [
                'sometimes',
                'string',
                'max:255',
                'min:2'
            ],
            'email' => [
                'sometimes',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                'min:10',
                Rule::unique('users', 'phone')->ignore($userId)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'The name must be a valid string.',
            'full_name.max' => 'The name may not be greater than 255 characters.',
            'full_name.min' => 'The name must be at least 2 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'phone.unique' => 'This phone number is already in use.',
            'phone.min' => 'The phone number must be at least 10 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'email' => 'email address',
            'phone' => 'phone number',
        ];
    }
}
