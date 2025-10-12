<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'message' => ['required', 'string', 'max:1000'],
            'sender_id' => ['nullable', 'string', 'max:11'],
            'lang' => ['nullable', 'string', 'in:en,ar'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient.required' => 'Phone number is required',
            'recipient.regex' => 'Phone number must be between 10-15 digits',
            'message.required' => 'Message content is required',
            'message.max' => 'Message cannot exceed 1000 characters',
            'sender_id.max' => 'Sender ID cannot exceed 11 characters',
            'lang.in' => 'Language must be either en or ar',
        ];
    }
}

