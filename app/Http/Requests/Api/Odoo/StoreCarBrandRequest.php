<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'nullable|string|max:255|unique:car_brands,odoo_id',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'name_kr' => 'nullable|string|max:255',
            'media' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.required' => 'Odoo id is required',
            'name.required' => 'Name is required',
        ];
    }
    
 
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('media') && $this->media !== null) {
                $media = $this->media;
                
                if ($media instanceof \Illuminate\Http\UploadedFile) {
                    if (!in_array($media->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'])) {
                        $validator->errors()->add('media', 'Media must be an image file (jpeg, png, gif, bmp, webp)');
                    }
                    if ($media->getSize() > 5242880) { 
                        $validator->errors()->add('media', 'Media file size must not exceed 5MB');
                    }
                }
                elseif (is_string($media)) {
                } else {
                    $validator->errors()->add('media', 'Media must be either a file upload or a base64 string');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'odoo_id' => 'odoo id',
            'name' => 'name',
            'media' => 'media',
        ];
    }
}