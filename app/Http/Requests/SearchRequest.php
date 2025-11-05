<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
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
            'search' => 'nullable|string|max:255',
            'brand_id' => 'nullable|integer|exists:car_brands,id',
            'model_id' => 'nullable|integer|exists:car_models,id',
            'condition' => 'nullable|string|in:new,used',
            'year_from' => 'nullable|integer|min:1990|max:' . date('Y'),
            'year_to' => 'nullable|integer|min:1990|max:' . date('Y') . '|gte:year_from',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0|gte:price_min',
            'mileage_min' => 'nullable|integer|min:0',
            'mileage_max' => 'nullable|integer|min:0|gte:mileage_min',
            'body_type_id' => 'nullable|integer|exists:body_types,id',
            'fuel_type_id' => 'nullable|integer|exists:fuel_types,id',
            'transmission_id' => 'nullable|integer|exists:transmissions,id',
            'exterior_color_id' => 'nullable|integer|exists:exterior_colors,id',
            'drive_type_id' => 'nullable|integer|exists:drive_types,id',
            'cylinder_id' => 'nullable|integer|exists:cylinder_options,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'import_type_id' => 'nullable|integer|exists:import_types,id',
            'has_warranty' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year_to.gte' => 'The year to must be greater than or equal to year from.',
            'price_max.gte' => 'The maximum price must be greater than or equal to minimum price.',
            'mileage_max.gte' => 'The maximum mileage must be greater than or equal to minimum mileage.',
            'brand_id.exists' => 'The selected brand does not exist.',
            'model_id.exists' => 'The selected model does not exist.',
            'body_type_id.exists' => 'The selected body type does not exist.',
            'fuel_type_id.exists' => 'The selected fuel type does not exist.',
            'transmission_id.exists' => 'The selected transmission does not exist.',
            'exterior_color_id.exists' => 'The selected exterior color does not exist.',
            'drive_type_id.exists' => 'The selected drive type does not exist.',
            'cylinder_id.exists' => 'The selected cylinder option does not exist.',
            'city_id.exists' => 'The selected city does not exist.',
        ];
    }
}
