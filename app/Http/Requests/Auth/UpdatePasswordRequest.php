<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Current password is required',
            'current_password.string' => 'Current password must be a string',
            'new_password.required' => 'New password is required',
            'new_password.string' => 'New password must be a string',
            'new_password.min' => 'New password must be at least 8 characters',
            'new_password.confirmed' => 'New password confirmation does not match',
            'new_password_confirmation.required' => 'New password confirmation is required',
            'new_password_confirmation.string' => 'New password confirmation must be a string',
            'new_password_confirmation.min' => 'New password confirmation must be at least 8 characters',
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'current password',
            'new_password' => 'new password',
            'new_password_confirmation' => 'new password confirmation',
        ];
    }
}
