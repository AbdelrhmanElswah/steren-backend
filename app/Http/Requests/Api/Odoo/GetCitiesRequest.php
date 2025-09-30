<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class GetCitiesRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'id' => 'sometimes|integer|min:1',
            'odoo_id' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'search.string' => 'Search term must be a string.',
            'search.max' => 'Search term cannot exceed 255 characters.',
            'id.integer' => 'ID must be an integer.',
            'id.min' => 'ID must be at least 1.',
            'odoo_id.string' => 'Odoo ID must be a string.',
            'odoo_id.max' => 'Odoo ID cannot exceed 255 characters.',
            'per_page.integer' => 'Per page must be an integer.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 100.',
            'page.integer' => 'Page must be an integer.',
            'page.min' => 'Page must be at least 1.',
        ];
    }
}
