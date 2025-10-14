<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic required fields
            'odoo_id' => 'nullable|string|max:255|unique:car_listings,odoo_id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'user_id' => 'required|exists:users,id',
            
            // Database required fields
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'mileage' => 'required|integer|min:0',
            'origin_country' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'car_brand_id' => 'required|exists:car_brands,id',
            'car_body_type_id' => 'required|exists:body_types,id',
            
            // Optional fields
            'vin' => 'nullable|string|max:255',
            'price_currency' => 'nullable|string|max:3',
            'trim' => 'nullable|string|max:255',
            'doors' => 'nullable|integer|min:1|max:10',
            'gcc_import' => 'nullable|boolean',
            'plate_status' => 'nullable|string|max:255',
            'condition_type' => 'nullable|string|max:255',
            'has_warranty' => 'nullable|boolean',
            'is_inspected' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'has_insurance' => 'nullable|boolean',
            'studio_id' => 'nullable|exists:studios,id',
            'car_model_id' => 'nullable|exists:car_models,id',
            'car_condition_id' => 'nullable|exists:car_conditions,id',
            'mileage_type_id' => 'nullable|exists:mileage_types,id',
            'import_type_id' => 'nullable|exists:import_types,id',
            'fuel_type_id' => 'nullable|exists:fuel_types,id',
            'transmission_id' => 'nullable|exists:transmissions,id',
            'exterior_color_id' => 'nullable|exists:exterior_colors,id',
            'drive_type_id' => 'nullable|exists:drive_types,id',
            'warranty_status_id' => 'nullable|exists:warranty_statuses,id',
            'cylinder_id' => 'nullable|exists:cylinder_options,id',
            'engine_size' => 'nullable|numeric|min:0',
            'engine_type' => 'nullable|string|max:255',
            'horsepower' => 'nullable|integer|min:0',
            'torque' => 'nullable|numeric|min:0',
            'acceleration_0_100' => 'nullable|numeric|min:0',
            'top_speed' => 'nullable|integer|min:0',
            'seating_capacity' => 'nullable|integer|min:1|max:20',
            'mileage_efficiency' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:255',
            'status_id' => 'nullable|exists:statuses,id',
            'operator_decision' => 'nullable|string|max:255',
            'is_featured' => 'nullable|boolean',
            'is_new_arrival' => 'nullable|boolean',
            'features' => 'nullable|string',
            'specifications' => 'nullable|string',
            'is_promoted' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'title.required' => 'Title is required',
            'description.required' => 'Description is required',
            'price.required' => 'Price is required',
            'user_id.required' => 'User id is required',
            'year.required' => 'Year is required',
            'mileage.required' => 'Mileage is required',
            'origin_country.required' => 'Origin country is required',
            'location_id.required' => 'Location id is required',
            'car_brand_id.required' => 'Car brand id is required',
            'car_body_type_id.required' => 'Car body type id is required',
        ];
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'title' => 'title',
            'description' => 'description',
            'price' => 'price',
            'user_id' => 'user id',
        ];
    }
}