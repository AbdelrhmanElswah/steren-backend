<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrandSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => 'required|string|max:255|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
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
            'search.required' => 'Search term is required.',
            'search.min' => 'Search term must be at least 1 character.',
            'search.max' => 'Search term cannot exceed 255 characters.',
            'limit.max' => 'Limit cannot exceed 50 results.',
        ];
    }
}
