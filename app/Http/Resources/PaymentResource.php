<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        return [
            'id' => $this->id,

            'method' => $this->method?->value,

            'method_label' => match ($this->method?->value) {
                'CASH' => 'Efectivo',
                'YAPE' => 'Yape',
                'BANK_TRANSFER' => 'Transferencia bancaria',
                'ONLINE_PAYMENT' => 'Pago en línea',
                default => 'Desconocido',
            },

            'amount' => $this->amount,

            'status' => $this->status?->value,

            'status_label' => match ($this->status?->value) {
                'PENDING' => 'Pendiente',
                'APPROVED' => 'Aprobado',
                'REJECTED' => 'Rechazado',
                'CANCELLED' => 'Cancelado',
                'REFUNDED' => 'Reembolsado',
                default => 'Desconocido',
            },

            'transaction_code' => $this->transaction_code,

            'payment_date' => $this->payment_date,

            'created_at' => $this->created_at,
        ];
    }
}
