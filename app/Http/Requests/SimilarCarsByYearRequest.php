<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimilarCarsByYearRequest extends FormRequest
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
            'year' => 'required|integer|min:1990|max:' . date('Y'),
            'year_tolerance' => 'nullable|integer|min:1|max:10',
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
            'year.required' => 'Year is required.',
            'year.min' => 'Year must be at least 1990.',
            'year.max' => 'Year cannot exceed current year.',
            'year_tolerance.min' => 'Year tolerance must be at least 1 year.',
            'year_tolerance.max' => 'Year tolerance cannot exceed 10 years.',
        ];
    }
}
