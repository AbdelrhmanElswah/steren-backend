<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VinDecodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vin' => [
                'required',
                'string',
                'size:17',
                'regex:/^[A-HJ-NPR-Z0-9]{17}$/i', // VIN format validation (no I, O, Q)
            ],
            'cache' => 'sometimes|boolean',
            'save_to_database' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vin.required' => 'VIN is required',
            'vin.size' => 'VIN must be exactly 17 characters long',
            'vin.regex' => 'VIN contains invalid characters. VIN cannot contain I, O, or Q',
            'cache.boolean' => 'Cache must be a boolean value',
            'save_to_database.boolean' => 'Save to database must be a boolean value',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vin' => 'VIN',
            'cache' => 'cache',
            'save_to_database' => 'save to database',
        ];
    }
}
