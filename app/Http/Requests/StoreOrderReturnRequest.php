<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderReturnRequest extends FormRequest
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
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],

            'return_date' => [
                'nullable',
                'date',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.order_detail_id' => [
                'required',
                'integer',
                'exists:order_details,id',
                'distinct',
            ],

            'details.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'details.required' =>
                'Debe indicar al menos un producto a devolver.',

            'details.array' =>
                'Los detalles de devolución deben ser un arreglo.',

            'details.min' =>
                'Debe indicar al menos un producto a devolver.',

            'details.*.order_detail_id.required' =>
                'El detalle de venta es obligatorio.',

            'details.*.order_detail_id.exists' =>
                'El detalle de venta indicado no existe.',

            'details.*.order_detail_id.distinct' =>
                'No puede repetir el mismo detalle de venta.',

            'details.*.quantity.required' =>
                'La cantidad a devolver es obligatoria.',

            'details.*.quantity.numeric' =>
                'La cantidad debe ser numérica.',

            'details.*.quantity.gt' =>
                'La cantidad a devolver debe ser mayor que cero.',

            'reason.max' =>
                'El motivo no puede superar los 500 caracteres.',

            'return_date.date' =>
                'La fecha de devolución no es válida.',
        ];
    }
}
