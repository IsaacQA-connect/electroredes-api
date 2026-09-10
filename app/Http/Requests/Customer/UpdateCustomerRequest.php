<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => [
                'nullable',
                'string',
                'max:20',
            ],

            'document_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
