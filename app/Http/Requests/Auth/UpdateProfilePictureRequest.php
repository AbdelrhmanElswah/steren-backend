<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePictureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_picture' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ];
    }

    public function messages(): array
    {
        return [
            'profile_picture.required' => 'Profile picture is required',
            'profile_picture.file' => 'Profile picture must be a file',
            'profile_picture.image' => 'Profile picture must be an image',
            'profile_picture.mimes' => 'Profile picture must be a JPEG, PNG, JPG, GIF, or WebP file',
            'profile_picture.max' => 'Profile picture cannot exceed 5MB',
        ];
    }

    public function attributes(): array
    {
        return [
            'profile_picture' => 'profile picture',
        ];
    }
}
