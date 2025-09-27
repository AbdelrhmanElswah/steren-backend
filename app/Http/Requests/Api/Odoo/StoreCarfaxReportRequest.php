<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarfaxReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:carfax_reports,odoo_id',
            'car_listing_id' => 'required|exists:car_listings,id',
            'report_data' => 'required|string|max:255',
            'report_date' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'car_listing_id.required' => 'Car listing id is required',
            'report_data.required' => 'Report data is required',
            'report_date.required' => 'Report date is required',
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