<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:car_models,odoo_id',
            'name' => 'required|string|max:255',
            'car_brand_id' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'name.required' => 'Name is required',
            'car_brand_id.required' => 'Car brand id is required',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'name' => 'name',
            'car_brand_id' => 'car brand id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('car_brand_id')) {
                $carBrandId = $this->car_brand_id;
                
                // Check if it's a numeric ID (Laravel ID)
                if (is_numeric($carBrandId)) {
                    $exists = \App\Models\CarBrand::where('id', $carBrandId)->exists();
                    if (!$exists) {
                        $validator->errors()->add('car_brand_id', 'The selected car brand does not exist.');
                    }
                } else {
                    // Check if it's an Odoo ID
                    $exists = \App\Models\CarBrand::where('odoo_id', $carBrandId)->exists();
                    if (!$exists) {
                        $validator->errors()->add('car_brand_id', 'The selected car brand with Odoo ID does not exist.');
                    }
                }
            }
        });
    }
}