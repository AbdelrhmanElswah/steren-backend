<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimilarCarsByPriceRequest extends FormRequest
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
            'price' => 'required|numeric|min:0',
            'price_tolerance' => 'nullable|numeric|min:0.1|max:1',
            'limit' => 'nullable|integer|min:1|max:20',
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
            'price.required' => 'Price is required.',
            'price.min' => 'Price must be at least 0.',
            'price_tolerance.min' => 'Price tolerance must be at least 0.1 (10%).',
            'price_tolerance.max' => 'Price tolerance cannot exceed 1 (100%).',
        ];
    }
}
