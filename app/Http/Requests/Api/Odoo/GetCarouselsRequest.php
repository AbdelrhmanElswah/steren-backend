<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class GetCarouselsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'id' => ['nullable', 'integer'],
            'odoo_id' => ['nullable', 'string'],
            'active_only' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:title,start_date,end_date,created_at,sort_order'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
