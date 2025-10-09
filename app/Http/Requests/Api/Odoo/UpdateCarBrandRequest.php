<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarBrandRequest extends FormRequest
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
                Rule::unique('car_brands', 'odoo_id')->ignore($this->route('id') ?? $this->route('odooId'))
            ],
            'name' => 'sometimes|string|max:255',
            'media' => 'sometimes|nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'odoo_id.string' => 'Odoo id must be a string',
            'name.string' => 'Name must be a string',
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