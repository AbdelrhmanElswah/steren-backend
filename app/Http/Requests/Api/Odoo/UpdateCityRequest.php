<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cityId = $this->route('id') ?? $this->route('odooId');
        
        return [
            'odoo_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('cities', 'odoo_id')->ignore($cityId)
            ],
            'name' => 'sometimes|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
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
