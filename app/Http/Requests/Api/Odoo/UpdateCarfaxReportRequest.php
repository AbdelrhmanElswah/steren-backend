<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarfaxReportRequest extends FormRequest
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
                Rule::unique('carfax_reports', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'car_listing_id' => 'sometimes|exists:car_listings,id',
            'report_data' => 'sometimes|string|max:255',
            'report_date' => 'sometimes|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'car_listing_id.string' => 'Car listing id must be a string',
            'report_data.string' => 'Report data must be a string',
            'report_date.string' => 'Report date must be a string',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'car_listing_id' => 'car listing id',
            'report_data' => 'report data',
            'report_date' => 'report date',
        ];
    }
}