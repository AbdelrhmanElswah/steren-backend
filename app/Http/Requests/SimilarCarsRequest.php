<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimilarCarsRequest extends FormRequest
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
            'limit' => 'nullable|integer|min:1|max:20',
            'brand_id' => 'nullable|integer|exists:car_brands,id',
            'model_id' => 'nullable|integer|exists:car_models,id',
            'body_type_id' => 'nullable|integer|exists:body_types,id',
            'fuel_type_id' => 'nullable|integer|exists:fuel_types,id',
            'transmission_id' => 'nullable|integer|exists:transmissions,id',
            'drive_type_id' => 'nullable|integer|exists:drive_types,id',
            'year_min' => 'nullable|integer|min:1990|max:' . date('Y'),
            'year_max' => 'nullable|integer|min:1990|max:' . date('Y') . '|gte:year_min',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0|gte:price_min',
            'mileage_min' => 'nullable|integer|min:0',
            'mileage_max' => 'nullable|integer|min:0|gte:mileage_min',
            'exclude_id' => 'nullable|integer|exists:car_listings,id',
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
            'year_max.gte' => 'The maximum year must be greater than or equal to minimum year.',
            'price_max.gte' => 'The maximum price must be greater than or equal to minimum price.',
            'mileage_max.gte' => 'The maximum mileage must be greater than or equal to minimum mileage.',
            'brand_id.exists' => 'The selected brand does not exist.',
            'model_id.exists' => 'The selected model does not exist.',
            'body_type_id.exists' => 'The selected body type does not exist.',
            'fuel_type_id.exists' => 'The selected fuel type does not exist.',
            'transmission_id.exists' => 'The selected transmission does not exist.',
            'drive_type_id.exists' => 'The selected drive type does not exist.',
            'exclude_id.exists' => 'The car listing to exclude does not exist.',
        ];
    }
}
