<?php

namespace App\Http\Requests\Product;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'unique:products,code',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',
                'unique:products,barcode',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'unit' => [
                'required',
                'string',
                'max:20',
            ],

            'cost' => [
                'required',
                'numeric',
                'min:0',
            ],

            'sale_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'minimum_stock' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
