<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
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

            'supplier' => [
                'id' => $this->supplier?->id,
                'business_name' => $this->supplier?->business_name,
            ],

            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],

            'purchase_date' => $this->purchase_date,

            'document_type' => $this->document_type,
            'document_number' => $this->document_number,

            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,

            'status' => $this->status?->value,

            'notes' => $this->notes,

            'details' => $this->whenLoaded(
                'details',
                fn () => $this->details->map(fn ($detail) => [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product?->name,
                    'quantity' => $detail->quantity,
                    'unit_cost' => $detail->unit_cost,
                    'subtotal' => $detail->subtotal,
                ])
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
