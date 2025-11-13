<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthorizedAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odoo_id' => 'sometimes|required|string|max:255|unique:authorized_agencies,odoo_id,' . $this->route('odooId') . ',odoo_id',
            'name' => 'sometimes|required|string|max:255',
            'partner_id' => 'sometimes|required|string|max:255',
            'media' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'partner_id.required' => 'Partner ID is required',
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
                } elseif (is_string($media)) {
                    // Allow base64 strings
                } else {
                    $validator->errors()->add('media', 'Media must be either a file upload or a base64 string');
                }
            }
        });
    }
}

