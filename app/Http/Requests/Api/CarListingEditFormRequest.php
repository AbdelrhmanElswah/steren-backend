<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CarListing;

class CarListingEditFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    
    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $carListingId = $this->input('car_listing_id');
            
            if ($carListingId) {
                $carListing = CarListing::find($carListingId);
                
                if ($carListing) {
                    $allowedStatuses = ['active', 'paused', 'rejected'];
                    $currentStatus = $carListing->status ?? 'pending';
                    
                    if (!in_array($currentStatus, $allowedStatuses)) {
                        $validator->errors()->add(
                            'car_listing_id',
                            "Cannot edit car listing. Only listings with status 'active', 'paused', or 'rejected' can be edited. Current status: {$currentStatus}"
                        );
                    }
                }
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'car_listing_id' => 'required|integer|exists:car_listings,id',
            
            // Car Info - Form Data Fields (all optional for edits)
            'vin' => 'nullable|string|size:17',
            'car_brand_id' => 'sometimes|integer|exists:car_brands,id',
            'car_model_id' => 'sometimes|integer|exists:car_models,id',
            'condition_type' => 'sometimes|string|in:new,used',
            'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'gcc_import' => 'nullable|string|max:255',
            'car_body_type_id' => 'sometimes|integer|exists:body_types,id',
            'engine_size' => 'nullable',
            'engine_unit' => 'nullable|string|max:10',
            'fuel_type_id' => 'nullable|integer|exists:fuel_types,id',
            'cylinder_id' => 'nullable|integer|exists:cylinder_options,id',
            'transmission_id' => 'nullable|integer|exists:transmissions,id',
            'seating_capacity' => 'nullable|integer|min:1|max:12',
            'exterior_color_id' => 'nullable|integer|exists:exterior_colors,id',
            'has_warranty' => 'nullable|boolean',
            'drive_type_id' => 'nullable|integer|exists:drive_types,id',
            'mileage_type_id' => 'nullable|integer|exists:mileage_types,id',
            'mileage' => 'sometimes|integer|min:0',
            'plate_status' => 'nullable|string|max:50',
            'damaged_areas' => 'nullable|array',
            'damaged_areas.*' => 'nullable',
            'doors' => 'nullable|integer|min:2|max:6',
            'trim' => 'nullable|string|max:100',
            'origin_country' => 'nullable|string|max:100',
            'is_inspected' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'has_insurance' => 'nullable|boolean',
            'city_id' => 'sometimes|integer|exists:cities,id',
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
            'forced_induction' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'status_id' => 'nullable|integer|exists:statuses,id',
            'operator_decision' => 'nullable|string|max:50',
            'is_featured' => 'nullable|boolean',
            'is_new_arrival' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*.title' => 'required_with:features|string',
            'features.*.description' => 'required_with:features|array',
            'features.*.description.*' => 'string',
            
            // Pricing
            'price_currency' => 'sometimes|string|size:3',
            'price' => 'sometimes|numeric|min:0',
            
            // Listing Details
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            
            // Existing image URLs to keep (from original listing)
            'existing_image_urls' => 'nullable|array|max:20',
            'existing_image_urls.*' => ['required', 'string', 'regex:/^https?:\/\/.+/'],
            
            // New images - File uploads (can be single file or array)
            'new_images' => 'nullable|array|max:20',
            'new_images.*' => 'file|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max per image
            
            // Legacy support: 'images' field (for backward compatibility)
            'images' => 'nullable|array|max:20',
            'images.*' => 'file|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max per image
            
            // Inspection Data
            'inspection_right_side' => 'nullable|boolean',
            'inspection_left_side' => 'nullable|boolean',
            'inspection_roof' => 'nullable|boolean',
            'inspection_front' => 'nullable|boolean',
            'inspection_back' => 'nullable|boolean',
            'inspection_file' => 'nullable|file|max:10240', // 10MB max
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'car_listing_id.required' => 'Car listing ID is required',
            'car_listing_id.exists' => 'The specified car listing does not exist',
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
            'images.max' => 'You can upload a maximum of 20 images.',
            'images.*.max' => 'Each image must not be larger than 5MB.',
            'inspection_file.max' => 'The inspection file must not be larger than 10MB.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle new_images field - ensure it's always an array
        if ($this->hasFile('new_images')) {
            $newImages = $this->file('new_images');
            // If it's a single file, wrap it in an array
            if (!is_array($newImages)) {
                $newImages = [$newImages];
            }
            $this->merge(['new_images' => $newImages]);
        }
        
        // Handle legacy images field - ensure it's always an array (for backward compatibility)
        if ($this->hasFile('images')) {
            $images = $this->file('images');
            // If it's a single file, wrap it in an array
            if (!is_array($images)) {
                $images = [$images];
            }
            $this->merge(['images' => $images]);
        }

        // Convert string boolean values to actual booleans
        $this->merge([
            'has_warranty' => filter_var($this->has_warranty, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'is_inspected' => filter_var($this->is_inspected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'is_verified' => filter_var($this->is_verified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'has_insurance' => filter_var($this->has_insurance, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'is_featured' => filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'is_new_arrival' => filter_var($this->is_new_arrival, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'inspection_right_side' => filter_var($this->inspection_right_side, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'inspection_left_side' => filter_var($this->inspection_left_side, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'inspection_roof' => filter_var($this->inspection_roof, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'inspection_front' => filter_var($this->inspection_front, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'inspection_back' => filter_var($this->inspection_back, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }
}
