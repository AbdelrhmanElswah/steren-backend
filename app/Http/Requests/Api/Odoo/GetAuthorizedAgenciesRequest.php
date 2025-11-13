<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class GetAuthorizedAgenciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'id' => 'sometimes|integer|min:1',
            'odoo_id' => 'sometimes|string|max:255',
            'partner_id' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}

