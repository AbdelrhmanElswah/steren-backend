<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:google,apple,facebook'],
            'firebase_token' => ['required', 'string'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'profile_picture' => ['nullable', 'string', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'Provider must be one of: google, apple, facebook',
            'firebase_token.required' => 'Firebase token is required for social authentication',
        ];
    }
}
