<?php

namespace App\Http\Requests\Order;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:COMPLETED,CANCELLED',
            ],
            'cancellation_reason' => [
                'required_if:status,CANCELLED',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'                       => 'El estado solo puede ser Completado o Cancelado.',
            'cancellation_reason.required_if' => 'Debes ingresar el motivo de la cancelación.',
        ];
    }
}