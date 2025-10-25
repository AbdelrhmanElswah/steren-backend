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
                'string',
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