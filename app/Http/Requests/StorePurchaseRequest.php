<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
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
            'supplier_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
            ],

            'purchase_date' => [
                'required',
                'date',
            ],

            'document_type' => [
                'nullable',
                'string',
                Rule::in(['BOLETA', 'FACTURA', 'OTRO']),
            ],

            'document_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'tax' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
                'distinct',
            ],

            'details.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'details.*.unit_cost' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
