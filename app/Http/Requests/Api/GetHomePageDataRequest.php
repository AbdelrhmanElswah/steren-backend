<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GetHomePageDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => 'nullable|integer|exists:cities,id',
            'top_picks_limit' => 'nullable|integer|min:1|max:50',
        ];
    }
}

