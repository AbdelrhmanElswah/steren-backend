<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:car_inspections,odoo_id',
            'car_listing_id' => 'required|exists:car_listings,id',
            'inspector_name' => 'required|string|max:255',
            'inspection_date' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'car_listing_id.required' => 'Car listing id is required',
            'inspector_name.required' => 'Inspector name is required',
            'inspection_date.required' => 'Inspection date is required',
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