<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('car_listings', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'car_info' => 'sometimes|array',
            'car_info.vin' => 'nullable|string|size:17',
            'car_info.brand' => 'sometimes|integer|exists:car_brands,id',
            'car_info.model' => 'sometimes|integer|exists:car_models,id',
            'car_info.condition' => 'sometimes|string|in:new,used',
            'car_info.year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'car_info.gcc_import' => 'nullable|string|max:255',
            'car_info.body_type' => 'sometimes|integer|exists:body_types,id',
            'car_info.engine_size' => 'nullable',
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
            'price_currency' => 'sometimes|string|size:3',
            'price' => 'sometimes|numeric|min:0',
            'seller_info' => 'sometimes|array',
            'seller_info.type' => 'sometimes|string|in:verified_seller,private_seller,dealer',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'images' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    // Dynamic validation based on user's dealer verification status
                    if ($this->user()) {
                        $user = $this->user();
                        $maxPhotos = 5; // Default for non-dealers
                        
                        // Basic dealers get limited photos
                        if ($user->isDealer()) {
                            $maxPhotos = 10; // Basic dealers get 10 photos
                        }
                        
                        // Verified dealers get more photos
                        if ($user->isVerifiedDealer()) {
                            $maxPhotos = 20; // Verified dealers get 20 photos
                        }
                        
                        if (count($value) > $maxPhotos) {
                            $fail("You can upload a maximum of {$maxPhotos} photos per listing. " . 
                                  ($user->isDealer() && !$user->isVerifiedDealer() ? 
                                   "Complete your dealer verification to upload more photos." : ""));
                        }
                    }
                }
            ],
            'images.*' => 'string', // base64 encoded images
            
            // Inspection data
            'inspection' => 'nullable|array',
            'inspection.right_side' => 'nullable|boolean',
            'inspection.left_side' => 'nullable|boolean',
            'inspection.roof' => 'nullable|boolean',
            'inspection.front' => 'nullable|boolean',
            'inspection.back' => 'nullable|boolean',
            
            // Inspection file
            'inspection_file' => 'nullable|string', // base64 encoded PDF data
            'inspection_file_name' => 'nullable|string|max:255',
            'user_id' => 'sometimes|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'car_info.vin.size' => 'VIN must be exactly 17 characters',
            'car_info.brand.exists' => 'Selected car brand does not exist',
            'car_info.model.exists' => 'Selected car model does not exist',
            'car_info.condition.in' => 'Car condition must be either new or used',
            'car_info.year.min' => 'Car year must be at least 1900',
            'car_info.year.max' => 'Car year cannot be in the future',
            'car_info.body_type.exists' => 'Selected body type does not exist',
            'car_info.mileage.min' => 'Mileage must be at least 0',
            'price_currency.size' => 'Price currency must be exactly 3 characters',
            'price.min' => 'Price must be at least 0',
            'seller_info.type.in' => 'Seller type must be verified_seller, private_seller, or dealer',
            'title.max' => 'Title cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 2000 characters',
            'user_id.exists' => 'User does not exist',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'car_info.brand' => 'car brand',
            'car_info.model' => 'car model',
            'car_info.condition' => 'car condition',
            'car_info.year' => 'car year',
            'car_info.body_type' => 'body type',
            'car_info.mileage' => 'mileage',
            'price_currency' => 'price currency',
            'price' => 'price',
            'seller_info.type' => 'seller type',
            'title' => 'title',
            'description' => 'description',
            'user_id' => 'user id',
        ];
    }
}