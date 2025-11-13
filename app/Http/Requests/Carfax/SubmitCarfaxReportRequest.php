<?php

namespace App\Http\Requests\Carfax;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCarfaxReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'car_listing_id' => ['required', 'integer', 'exists:car_listings,id'],
            'reason' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

