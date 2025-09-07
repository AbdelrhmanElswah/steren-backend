<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompletePasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}


