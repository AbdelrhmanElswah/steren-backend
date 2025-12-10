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
            'title' => 'required|array',
            'title.en' => 'required_with:title|string|max:255',
            'title.ar' => 'nullable|string|max:255',
            'title.kr' => 'nullable|string|max:255',
            'message' => 'required|array',
            'message.en' => 'required_with:message|string',
            'message.ar' => 'nullable|string',
            'message.kr' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'partner_id.required' => 'Partner ID is required',
            'type.required' => 'Notification type is required',
            'title.required' => 'Title object is required',
            'title.en.required_with' => 'Title in English (title.en) is required',
            'message.required' => 'Message object is required',
            'message.en.required_with' => 'Message in English (message.en) is required',
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

