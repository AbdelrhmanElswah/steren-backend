<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
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
                Rule::unique('locations', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:255',
            'latitude' => 'sometimes|string|max:255',
            'longitude' => 'sometimes|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'name.string' => 'Name must be a string',
            'address.string' => 'Address must be a string',
            'latitude.string' => 'Latitude must be a string',
            'longitude.string' => 'Longitude must be a string',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'name' => 'name',
            'address' => 'address',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
        ];
    }
}