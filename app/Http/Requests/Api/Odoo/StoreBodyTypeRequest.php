<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreBodyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:body_types,odoo_id',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'name_kr' => 'nullable|string|max:255',
            'media' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'name.required' => 'Name is required',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'name' => 'name',
            'media' => 'media',
        ];
    }
}