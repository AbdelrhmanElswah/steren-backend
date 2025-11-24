<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetFavoritesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fav_brand_ids' => 'sometimes|array',
            'fav_brand_ids.*' => 'integer|exists:car_brands,id',
            'fav_models_ids' => 'sometimes|array',
            'fav_models_ids.*' => 'integer|exists:car_models,id',
            'fav_body_type_ids' => 'sometimes|array',
            'fav_body_type_ids.*' => 'integer|exists:body_types,id',
            'fav_citie_ids' => 'sometimes|array',
            'fav_citie_ids.*' => 'integer|exists:cities,id',
        ];
    }

    public function messages(): array
    {
        return [
            'fav_brand_ids.array' => 'Favorite brand IDs must be an array',
            'fav_brand_ids.*.integer' => 'Each brand ID must be an integer',
            'fav_brand_ids.*.exists' => 'One or more brand IDs do not exist',
            'fav_models_ids.array' => 'Favorite model IDs must be an array',
            'fav_models_ids.*.integer' => 'Each model ID must be an integer',
            'fav_models_ids.*.exists' => 'One or more model IDs do not exist',
            'fav_body_type_ids.array' => 'Favorite body type IDs must be an array',
            'fav_body_type_ids.*.integer' => 'Each body type ID must be an integer',
            'fav_body_type_ids.*.exists' => 'One or more body type IDs do not exist',
            'fav_citie_ids.array' => 'Favorite city IDs must be an array',
            'fav_citie_ids.*.integer' => 'Each city ID must be an integer',
            'fav_citie_ids.*.exists' => 'One or more city IDs do not exist',
        ];
    }

    public function attributes(): array
    {
        return [
            'fav_brand_ids' => 'favorite brand IDs',
            'fav_models_ids' => 'favorite model IDs',
            'fav_body_type_ids' => 'favorite body type IDs',
            'fav_citie_ids' => 'favorite city IDs',
        ];
    }
}
