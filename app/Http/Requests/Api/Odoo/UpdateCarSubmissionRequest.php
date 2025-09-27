<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarSubmissionRequest extends FormRequest
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
                Rule::unique('car_submissions', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'user_id' => 'sometimes|exists:users,id',
            'car_brand_id' => 'sometimes|exists:car_brands,id',
            'car_model_id' => 'sometimes|exists:car_models,id',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'user_id.string' => 'User id must be a string',
            'car_brand_id.string' => 'Car brand id must be a string',
            'car_model_id.string' => 'Car model id must be a string',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'user_id' => 'user id',
            'car_brand_id' => 'car brand id',
            'car_model_id' => 'car model id',
        ];
    }
}