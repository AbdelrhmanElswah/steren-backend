<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class CreateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => 'required|string',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'partner_id.required' => 'Partner ID is required',
            'type.required' => 'Notification type is required',
            'title.required' => 'Title is required',
            'message.required' => 'Message is required',
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_id' => 'partner ID',
            'type' => 'notification type',
            'title' => 'title',
            'message' => 'message',
        ];
    }
}

