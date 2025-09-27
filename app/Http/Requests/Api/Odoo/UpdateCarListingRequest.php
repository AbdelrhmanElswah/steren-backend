<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarListingRequest extends FormRequest
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
                Rule::unique('car_listings', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'user_id' => 'sometimes|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'title.string' => 'Title must be a string',
            'description.string' => 'Description must be a string',
            'price.string' => 'Price must be a string',
            'user_id.string' => 'User id must be a string',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'title' => 'title',
            'description' => 'description',
            'price' => 'price',
            'user_id' => 'user id',
        ];
    }
}