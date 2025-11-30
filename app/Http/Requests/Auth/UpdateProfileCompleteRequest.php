<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        
        return [
            // Profile information fields (all optional)
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
            
            // Profile picture (optional)
            'profile_picture' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            
            // Password update fields (optional, but all required together if updating password)
            'current_password' => 'sometimes|required_with:new_password|string',
            'new_password' => 'sometimes|required_with:current_password|string|min:8|confirmed',
            'new_password_confirmation' => 'sometimes|required_with:new_password|string|min:8',
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
            'profile_picture.file' => 'Profile picture must be a file',
            'profile_picture.image' => 'Profile picture must be an image',
            'profile_picture.mimes' => 'Profile picture must be a JPEG, PNG, JPG, GIF, or WebP file',
            'profile_picture.max' => 'Profile picture cannot exceed 5MB',
            'current_password.required_with' => 'Current password is required when updating password',
            'current_password.string' => 'Current password must be a string',
            'new_password.required_with' => 'New password is required when updating password',
            'new_password.string' => 'New password must be a string',
            'new_password.min' => 'New password must be at least 8 characters',
            'new_password.confirmed' => 'New password confirmation does not match',
            'new_password_confirmation.required_with' => 'New password confirmation is required',
            'new_password_confirmation.string' => 'New password confirmation must be a string',
            'new_password_confirmation.min' => 'New password confirmation must be at least 8 characters',
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
            'profile_picture' => 'profile picture',
            'current_password' => 'current password',
            'new_password' => 'new password',
            'new_password_confirmation' => 'new password confirmation',
        ];
    }

    /**
     * Prepare the data for validation.
     * This is especially important for multipart/form-data requests.
     */
    protected function prepareForValidation(): void
    {
        // Convert string boolean values to actual booleans for multipart/form-data
        // This ensures boolean fields are properly validated
        $data = [];
        
        if ($this->has('notifications_enabled')) {
            $data['notifications_enabled'] = filter_var($this->input('notifications_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        if ($this->has('marketing_emails_enabled')) {
            $data['marketing_emails_enabled'] = filter_var($this->input('marketing_emails_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        // Merge the converted data back into the request
        if (!empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If any password field is provided, all three must be provided
            $hasCurrentPassword = $this->has('current_password');
            $hasNewPassword = $this->has('new_password');
            $hasNewPasswordConfirmation = $this->has('new_password_confirmation');

            // Check if any password field is provided
            if ($hasCurrentPassword || $hasNewPassword || $hasNewPasswordConfirmation) {
                // If any is provided, all must be provided
                if (!$hasCurrentPassword || !$hasNewPassword || !$hasNewPasswordConfirmation) {
                    $validator->errors()->add(
                        'current_password',
                        'To update password, you must provide current_password, new_password, and new_password_confirmation together.'
                    );
                }
            }
        });
    }
}

