<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBulkMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'string', 'in:sms,whatsapp'],
            'sender_id' => ['nullable', 'string', 'max:11'],
            'lang' => ['nullable', 'string', 'in:en,ar'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients.required' => 'Recipients array is required',
            'recipients.array' => 'Recipients must be an array',
            'recipients.min' => 'At least one recipient is required',
            'recipients.max' => 'Maximum 100 recipients allowed',
            'recipients.*.regex' => 'Each phone number must be between 10-15 digits',
            'message.required' => 'Message content is required',
            'message.max' => 'Message cannot exceed 1000 characters',
            'type.required' => 'Message type is required',
            'type.in' => 'Message type must be either sms or whatsapp',
            'sender_id.max' => 'Sender ID cannot exceed 11 characters',
            'lang.in' => 'Language must be either en or ar',
        ];
    }
}

