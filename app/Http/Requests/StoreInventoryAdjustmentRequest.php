<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryAdjustmentRequest extends FormRequest
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
            'movement_date' => [
                'required',
                'date',
                'before_or_equal:today', // Evita registros en el futuro
            ],

            'notes' => [
                'required',
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

            //'details.*.unit_cost' => [
              //  'nullable',
               // 'numeric',
                //'gte:0',
            //],
        ];
    }

    public function messages(): array
    {
        return [
            'details.*.product_id.distinct' => 'No puedes repetir el mismo producto en el detalle.',
        ];
    }
}
