<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CarListingCreateFormRequest extends FormRequest
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
            // Car Info - Form Data Fields (Flutter compatible)
            'vin' => 'nullable|string|size:17',
            'car_brand_id' => 'required|integer|exists:car_brands,id',
            'car_model_id' => 'required|integer|exists:car_models,id',
            'condition_type' => 'required|string|in:new,used',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'gcc_import' => 'nullable|string|max:255',
            'car_body_type_id' => 'required|integer|exists:body_types,id',
            'engine_size' => 'nullable|numeric|min:0|max:10',
            'fuel_type_id' => 'nullable|integer|exists:fuel_types,id',
            'cylinder_id' => 'nullable|integer|exists:cylinder_options,id',
            'transmission_id' => 'nullable|integer|exists:transmissions,id',
            'seating_capacity' => 'nullable|integer|min:1|max:12',
            'exterior_color_id' => 'nullable|integer|exists:exterior_colors,id',
            'has_warranty' => 'nullable|boolean',
            'drive_type_id' => 'nullable|integer|exists:drive_types,id',
            'mileage_type_id' => 'nullable|integer|exists:mileage_types,id',
            'mileage' => 'required|integer|min:0',
            'plate_status' => 'nullable|string|max:50',
            'doors' => 'nullable|integer|min:2|max:6',
            'trim' => 'nullable|string|max:100',
            'origin_country' => 'nullable|string|max:100',
            'is_inspected' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'has_insurance' => 'nullable|boolean',
            'city_id' => 'nullable|integer|exists:cities,id',
            'import_type_id' => 'nullable|integer|exists:import_types,id',
            'warranty_status_id' => 'nullable|integer|exists:warranty_statuses,id',
            'engine_type' => 'nullable|string|max:50',
            'horsepower' => 'nullable|integer|min:0',
            'torque' => 'nullable|numeric|min:0',
            'acceleration_0_100' => 'nullable|numeric|min:0',
            'top_speed' => 'nullable|integer|min:0',
            'mileage_efficiency' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:50',
            'status_id' => 'nullable|integer|exists:statuses,id',
            'operator_decision' => 'nullable|string|max:50',
            'is_featured' => 'nullable|boolean',
            'is_new_arrival' => 'nullable|boolean',
            'features' => 'nullable|string',
            'specifications' => 'nullable|string',
            
            // Pricing
            'price_currency' => 'required|string|size:3',
            'price' => 'required|numeric|min:0',
            
            // Seller Info (optional for Flutter compatibility)
            'seller_type' => 'nullable|string|in:verified_seller,private_seller,dealer',
            
            // Listing Details
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            
            // Images - File uploads
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max per image
            
            // Inspection Data
            'inspection_right_side' => 'nullable|boolean',
            'inspection_left_side' => 'nullable|boolean',
            'inspection_roof' => 'nullable|boolean',
            'inspection_front' => 'nullable|boolean',
            'inspection_back' => 'nullable|boolean',
            'inspection_file' => 'nullable|file|mimes:pdf|max:10240', // 10MB max for PDF
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
            'car_brand_id.exists' => 'The selected brand does not exist.',
            'car_model_id.exists' => 'The selected model does not exist.',
            'car_body_type_id.exists' => 'The selected body type does not exist.',
            'fuel_type_id.exists' => 'The selected fuel type does not exist.',
            'cylinder_id.exists' => 'The selected cylinder option does not exist.',
            'transmission_id.exists' => 'The selected transmission does not exist.',
            'exterior_color_id.exists' => 'The selected exterior color does not exist.',
            'drive_type_id.exists' => 'The selected drive type does not exist.',
            'mileage_type_id.exists' => 'The selected mileage type does not exist.',
            'city_id.exists' => 'The selected city does not exist.',
            'import_type_id.exists' => 'The selected import type does not exist.',
            'warranty_status_id.exists' => 'The selected warranty status does not exist.',
            'status_id.exists' => 'The selected status does not exist.',
            'images.max' => 'You can upload a maximum of 10 images.',
            'images.*.max' => 'Each image must not be larger than 5MB.',
            'inspection_file.max' => 'The inspection file must not be larger than 10MB.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string boolean values to actual booleans
        $this->merge([
            'inspection_right_side' => filter_var($this->inspection_right_side, FILTER_VALIDATE_BOOLEAN),
            'inspection_left_side' => filter_var($this->inspection_left_side, FILTER_VALIDATE_BOOLEAN),
            'inspection_roof' => filter_var($this->inspection_roof, FILTER_VALIDATE_BOOLEAN),
            'inspection_front' => filter_var($this->inspection_front, FILTER_VALIDATE_BOOLEAN),
            'inspection_back' => filter_var($this->inspection_back, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
