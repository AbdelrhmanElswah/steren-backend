<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:car_submissions,odoo_id',
            'user_id' => 'required|exists:users,id',
            'car_brand_id' => 'required|exists:car_brands,id',
            'car_model_id' => 'required|exists:car_models,id',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'user_id.required' => 'User id is required',
            'car_brand_id.required' => 'Car brand id is required',
            'car_model_id.required' => 'Car model id is required',
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