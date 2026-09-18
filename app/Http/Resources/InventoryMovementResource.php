<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
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
            'type' => $this->type?->value,
            'type_label' => match ($this->type?->value) {
                'INITIAL_STOCK' => 'Inventario inicial',
                'PURCHASE_ENTRY' => 'Entrada por compra',
                'SALE_EXIT' => 'Salida por venta',
                'SERVICE_EXIT' => 'Salida por servicio',
                'RETURN_ENTRY' => 'Entrada por devolución',
                'ADJUSTMENT_ENTRY' => 'Ajuste de entrada',
                'ADJUSTMENT_EXIT' => 'Ajuste de salida',
                default => 'Desconocido',
            },

            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'movement_date' => $this->movement_date,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'notes' => $this->notes,

            'details' => $this->whenLoaded(
                'details',
                fn () => $this->details->map(
                    fn ($detail) => [
                        'id' => $detail->id,
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product?->name,
                        'quantity' => $detail->quantity,
                        'unit_cost' => $detail->unit_cost,
                        'subtotal'     => round((float) $detail->quantity * (float) $detail->unit_cost, 2),
                    ]
                )
            ),

            'created_at' => $this->created_at,
        ];
    }
}
