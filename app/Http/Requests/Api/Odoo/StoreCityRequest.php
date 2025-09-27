<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:cities,odoo_id',
            'name' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'City name is required',
            'name.string' => 'City name must be a string',
            'name.max' => 'City name cannot exceed 255 characters',
            'odoo_id.string' => 'Odoo ID must be a string',
            'odoo_id.max' => 'Odoo ID cannot exceed 255 characters',
            'odoo_id.unique' => 'This Odoo ID already exists',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'city name',
            'odoo_id' => 'Odoo ID',
        ];
    }
}
