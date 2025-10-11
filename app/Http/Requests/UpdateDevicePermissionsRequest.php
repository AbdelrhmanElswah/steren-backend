<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDevicePermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // User can update their own permissions
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location' => 'required|boolean',
            'general_notifications' => 'required|boolean',
            'camera' => 'required|boolean',
            'photos' => 'required|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location.required' => 'Location permission is required',
            'location.boolean' => 'Location permission must be true or false',
            'general_notifications.required' => 'General notifications permission is required',
            'general_notifications.boolean' => 'General notifications permission must be true or false',
            'camera.required' => 'Camera permission is required',
            'camera.boolean' => 'Camera permission must be true or false',
            'photos.required' => 'Photos permission is required',
            'photos.boolean' => 'Photos permission must be true or false',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'location' => 'Location Permission',
            'general_notifications' => 'General Notifications Permission',
            'camera' => 'Camera Permission',
            'photos' => 'Photos Permission',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional validation logic can be added here if needed
            // For example, checking if user has certain roles or permissions
        });
    }
}
