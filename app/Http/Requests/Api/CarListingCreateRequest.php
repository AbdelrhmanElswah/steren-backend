<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CarListingCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // You can add authorization logic here if needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'car_info' => 'required|array',
            'car_info.vin' => 'nullable|string|size:17',
            'car_info.brand' => 'required|integer|exists:car_brands,id',
            'car_info.model' => 'required|integer|exists:car_models,id',
            'car_info.condition' => 'required|string|in:new,used',
            'car_info.year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'car_info.gcc_import' => 'nullable|string|max:255',
            'car_info.body_type' => 'required|integer|exists:body_types,id',
            'car_info.engine_size' => 'nullable|numeric|min:0|max:10',
            'car_info.fuel_type' => 'nullable|string|max:50',
            'car_info.cylinders' => 'nullable|integer|min:1|max:16',
            'car_info.transmission' => 'nullable|string|max:50',
            'car_info.seating_capacity' => 'nullable|integer|min:1|max:12',
            'car_info.exterior_colour' => 'nullable|string|max:50',
            'car_info.warranty' => 'nullable|string|in:yes,no',
            'car_info.drive_type' => 'nullable|string|max:50',
            'car_info.mileage_type' => 'nullable|string|in:KM,Miles',
            'car_info.mileage' => 'required|integer|min:0',
            'car_info.plate_status' => 'nullable|string|max:50',
            'price_currency' => 'required|string|size:3',
            'price' => 'required|numeric|min:0',
            'seller_info' => 'required|array',
            'seller_info.type' => 'required|string|in:verified_seller,private_seller,dealer',
            'title' => 'required|string|max:255',
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
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'car_info.required' => 'Car information is required',
            'car_info.vin.size' => 'VIN must be exactly 17 characters',
            'car_info.brand.required' => 'Car brand is required',
            'car_info.brand.exists' => 'Selected car brand does not exist',
            'car_info.model.required' => 'Car model is required',
            'car_info.model.exists' => 'Selected car model does not exist',
            'car_info.condition.required' => 'Car condition is required',
            'car_info.condition.in' => 'Car condition must be either new or used',
            'car_info.year.required' => 'Car year is required',
            'car_info.year.min' => 'Car year must be at least 1900',
            'car_info.year.max' => 'Car year cannot be in the future',
            'car_info.body_type.required' => 'Body type is required',
            'car_info.body_type.exists' => 'Selected body type does not exist',
            'car_info.mileage.required' => 'Mileage is required',
            'car_info.mileage.min' => 'Mileage must be at least 0',
            'price_currency.required' => 'Price currency is required',
            'price_currency.size' => 'Price currency must be exactly 3 characters',
            'price.required' => 'Price is required',
            'price.min' => 'Price must be at least 0',
            'seller_info.required' => 'Seller information is required',
            'seller_info.type.required' => 'Seller type is required',
            'seller_info.type.in' => 'Seller type must be verified_seller, private_seller, or dealer',
            'title.required' => 'Title is required',
            'title.max' => 'Title cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 2000 characters',
            'images.max' => 'Maximum 10 images allowed',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
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
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // You can modify the data before validation here if needed
        // For example, normalize certain fields
    }
}