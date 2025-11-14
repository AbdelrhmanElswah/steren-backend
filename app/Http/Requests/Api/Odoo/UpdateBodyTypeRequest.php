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
            'media' => 'media',
            'active' => 'active',
        ];
    }
}