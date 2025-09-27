<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('car_inspections', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'car_listing_id' => 'sometimes|exists:car_listings,id',
            'inspector_name' => 'sometimes|string|max:255',
            'inspection_date' => 'sometimes|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'car_listing_id.string' => 'Car listing id must be a string',
            'inspector_name.string' => 'Inspector name must be a string',
            'inspection_date.string' => 'Inspection date must be a string',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'car_listing_id' => 'car listing id',
            'inspector_name' => 'inspector name',
            'inspection_date' => 'inspection date',
        ];
    }
}