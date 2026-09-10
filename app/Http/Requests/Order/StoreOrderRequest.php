<?php

namespace App\Http\Requests\Order;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],

            'channel' => [
                'required',
                'string',
                Rule::in(['POS', 'WEB']),
            ],

            'order_date' => [
                'nullable',
                'date',
            ],

            'discount' => [
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

            'payments' => [
                'nullable',
                'array',
            ],

            'payments.*.method' => [
                'required',
                'string',
                Rule::enum(PaymentMethod::class),
            ],

            'payments.*.amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payments.*.transaction_code' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'details.required' => 'La venta debe contener al menos un producto.',
            'details.*.product_id.required' => 'El producto es obligatorio.',
            'details.*.product_id.exists' => 'El producto seleccionado no existe.',
            'details.*.product_id.distinct' => 'No se puede repetir un producto en la misma venta.',
            'details.*.quantity.required' => 'La cantidad es obligatoria.',
            'details.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
            'payments.*.method.required' => 'El método de pago es obligatorio.',
            'payments.*.amount.required' => 'El monto del pago es obligatorio.',
            'payments.*.amount.gt' => 'El monto del pago debe ser mayor que cero.',
        ];
    }
}
