<?php

namespace App\Http\Requests\Api\AuthorizedAgencyModel;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuthorizedAgencyModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'car_model_id' => ['required', 'exists:car_models,id'],
            'gcc_import' => ['nullable', 'string', 'max:255'],
            'engine_size' => ['nullable', 'string', 'max:255'],
            'forced_induction' => ['nullable', 'string', 'max:50'],
            'fuel_type' => ['nullable', 'string', 'max:255'],
            'cylinders' => ['nullable', 'string', 'max:255'],
            'transmission' => ['nullable', 'string', 'max:255'],
            'seating_capacity' => ['nullable', 'integer', 'min:1'],
            'drive_type' => ['nullable', 'string', 'max:255'],
            'price_start_from' => ['nullable', 'numeric', 'min:0'],
            'horse_power' => ['nullable', 'integer', 'min:0'],
            'length_from' => ['nullable', 'numeric', 'min:0'],
            'length_to' => ['nullable', 'numeric', 'min:0'],
            'width_from' => ['nullable', 'numeric', 'min:0'],
            'width_to' => ['nullable', 'numeric', 'min:0'],
            'height_from' => ['nullable', 'numeric', 'min:0'],
            'height_to' => ['nullable', 'numeric', 'min:0'],
            'torque' => ['nullable', 'string', 'max:255'],
            'acceleration' => ['nullable', 'numeric', 'min:0'],
            'top_speed' => ['nullable', 'integer', 'min:0'],

            'outer_image' => ['required_without:outer_image_base64', 'file', 'mimes:jpg,jpeg,png,webp,gif,bmp'],
            'outer_image_base64' => ['required_without:outer_image', 'nullable', 'string'],

            'inner_images' => ['nullable', 'array', 'max:12'],
            'inner_images.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,bmp'],

            'inner_images_base64' => ['nullable', 'array', 'max:12'],
            'inner_images_base64.*' => ['nullable', 'string'],
        ];
    }
}


