<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBodyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'name_kr' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|string',
            'active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'name' => 'name',
            'description' => 'description',
            'media' => 'media',
            'active' => 'active',
        ];
    }
}