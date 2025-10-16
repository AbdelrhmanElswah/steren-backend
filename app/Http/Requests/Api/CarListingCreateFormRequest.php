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
            // Car Info - Form Data Fields
            'vin' => 'nullable|string|size:17',
            'brand_id' => 'required|integer|exists:car_brands,id',
            'model_id' => 'required|integer|exists:car_models,id',
            'condition' => 'required|string|in:new,used',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'gcc_import' => 'nullable|string|max:255',
            'body_type_id' => 'required|integer|exists:body_types,id',
            'engine_size' => 'nullable|numeric|min:0|max:10',
            'fuel_type_id' => 'nullable|integer|exists:fuel_types,id',
            'cylinder_id' => 'nullable|integer|exists:cylinder_options,id',
            'transmission_id' => 'nullable|integer|exists:transmissions,id',
            'seating_capacity' => 'nullable|integer|min:1|max:12',
            'exterior_color_id' => 'nullable|integer|exists:exterior_colors,id',
            'warranty' => 'nullable|string|in:yes,no',
            'drive_type_id' => 'nullable|integer|exists:drive_types,id',
            'mileage_type_id' => 'nullable|integer|exists:mileage_types,id',
            'mileage' => 'required|integer|min:0',
            'plate_status' => 'nullable|string|max:50',
            
            // Pricing
            'price_currency' => 'required|string|size:3',
            'price' => 'required|numeric|min:0',
            
            // Seller Info
            'seller_type' => 'required|string|in:verified_seller,private_seller,dealer',
            
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
            'brand_id.exists' => 'The selected brand does not exist.',
            'model_id.exists' => 'The selected model does not exist.',
            'body_type_id.exists' => 'The selected body type does not exist.',
            'fuel_type_id.exists' => 'The selected fuel type does not exist.',
            'cylinder_id.exists' => 'The selected cylinder option does not exist.',
            'transmission_id.exists' => 'The selected transmission does not exist.',
            'exterior_color_id.exists' => 'The selected exterior color does not exist.',
            'drive_type_id.exists' => 'The selected drive type does not exist.',
            'mileage_type_id.exists' => 'The selected mileage type does not exist.',
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
