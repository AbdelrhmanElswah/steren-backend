<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarModelRequest extends FormRequest
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
                'max:255',
                Rule::unique('car_models', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'name' => 'sometimes|string|max:255',
            'car_brand_id' => 'sometimes',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'name.string' => 'Name must be a string',
            'car_brand_id.string' => 'Car brand id must be a string',
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
                
                // First, try to find by Odoo ID (primary use case)
                $brand = \App\Models\CarBrand::where('odoo_id', $carBrandId)->first();
                if ($brand) {
                    return; // Found by Odoo ID, validation passes
                }
                
                // If not found by Odoo ID and it's numeric, try as Laravel ID
                if (is_numeric($carBrandId)) {
                    $brand = \App\Models\CarBrand::where('id', $carBrandId)->first();
                    if ($brand) {
                        return; // Found by Laravel ID, validation passes
                    }
                }
                
                // If we get here, the car brand doesn't exist
                $validator->errors()->add('car_brand_id', 'The selected car brand does not exist.');
            }
        });
    }
}