<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CarListing;

class CarListingEditRequest extends FormRequest
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
            'car_info' => 'sometimes|array',
            'car_info.vin' => 'nullable|string|size:17',
            'car_info.brand' => 'sometimes|integer|exists:car_brands,id',
            'car_info.model' => 'sometimes|integer|exists:car_models,id',
            'car_info.condition' => 'sometimes|string|in:new,used',
            'car_info.year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'car_info.gcc_import' => 'nullable|string|max:255',
            'car_info.body_type' => 'sometimes|integer|exists:body_types,id',
            'car_info.engine_size' => 'nullable|numeric|min:0|max:10',
            'car_info.fuel_type' => 'nullable|string|max:50',
            'car_info.cylinders' => 'nullable|integer|min:1|max:16',
            'car_info.transmission' => 'nullable|string|max:50',
            'car_info.seating_capacity' => 'nullable|integer|min:1|max:12',
            'car_info.exterior_colour' => 'nullable|string|max:50',
            'car_info.warranty' => 'nullable|string|in:yes,no',
            'car_info.drive_type' => 'nullable|string|max:50',
            'car_info.mileage_type' => 'nullable|string|in:KM,Miles',
            'car_info.mileage' => 'sometimes|integer|min:0',
            'car_info.plate_status' => 'nullable|string|max:50',
            'car_info.damaged_areas' => 'nullable|array',
            'car_info.damaged_areas.*' => 'nullable',
            'price_currency' => 'sometimes|string|size:3',
            'price' => 'sometimes|numeric|min:0',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'city_id' => 'sometimes|integer|exists:cities,id',
            // Existing image URLs to keep (from original listing)
            'existing_image_urls' => 'nullable|array|max:20',
            'existing_image_urls.*' => 'url',
            
            // New images - Base64 encoded images
            'new_images' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if ($this->user()) {
                        $user = $this->user();
                        $maxPhotos = 5;
                        
                        if ($user->isDealer()) {
                            $maxPhotos = 10;
                        }
                        
                        if ($user->isVerifiedDealer()) {
                            $maxPhotos = 20;
                        }
                        
                        if (count($value) > $maxPhotos) {
                            $fail("You can upload a maximum of {$maxPhotos} photos per listing.");
                        }
                    }
                }
            ],
            'new_images.*' => 'string',
            
            // Legacy support: 'images' field (for backward compatibility)
            'images' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if ($this->user()) {
                        $user = $this->user();
                        $maxPhotos = 5;
                        
                        if ($user->isDealer()) {
                            $maxPhotos = 10;
                        }
                        
                        if ($user->isVerifiedDealer()) {
                            $maxPhotos = 20;
                        }
                        
                        if (count($value) > $maxPhotos) {
                            $fail("You can upload a maximum of {$maxPhotos} photos per listing.");
                        }
                    }
                }
            ],
            'images.*' => 'string',
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
            'car_info.vin.size' => 'VIN must be exactly 17 characters',
            'car_info.brand.exists' => 'The selected brand does not exist',
            'car_info.model.exists' => 'The selected model does not exist',
            'car_info.condition.in' => 'Condition must be either "new" or "used"',
            'car_info.year.min' => 'Year must be at least 1900',
            'car_info.year.max' => 'Year cannot be in the future',
            'car_info.body_type.exists' => 'The selected body type does not exist',
            'car_info.mileage.min' => 'Mileage must be at least 0',
            'price.min' => 'Price must be at least 0',
            'price_currency.size' => 'Price currency must be exactly 3 characters',
            'title.max' => 'Title cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 2000 characters',
        ];
    }
}
