<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        
        return [
            'full_name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId)
            ],
            'birthday' => 'sometimes|date|before:today',
            'notifications_enabled' => 'sometimes|boolean',
            'marketing_emails_enabled' => 'sometimes|boolean',
            'notification_preferences' => 'sometimes|array',
            'language_id' => 'sometimes|exists:languages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'Full name must be a string',
            'full_name.max' => 'Full name cannot exceed 255 characters',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already taken',
            'phone.string' => 'Phone number must be a string',
            'phone.unique' => 'This phone number is already taken',
            'birthday.date' => 'Birthday must be a valid date',
            'birthday.before' => 'Birthday must be before today',
            'notifications_enabled.boolean' => 'Notifications enabled must be true or false',
            'marketing_emails_enabled.boolean' => 'Marketing emails enabled must be true or false',
            'notification_preferences.array' => 'Notification preferences must be an array',
            'language_id.exists' => 'Selected language does not exist',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'email' => 'email',
            'phone' => 'phone number',
            'birthday' => 'birthday',
            'notifications_enabled' => 'notifications enabled',
            'marketing_emails_enabled' => 'marketing emails enabled',
            'notification_preferences' => 'notification preferences',
            'language_id' => 'language',
        ];
    }
}
