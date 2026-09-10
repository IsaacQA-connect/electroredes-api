<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
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

            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'code' => $this->product->code,
                    'barcode' => $this->product->barcode,
                    'name' => $this->product->name,
                    'unit' => $this->product->unit,
                ];
            }),

            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,

            'created_at' => $this->created_at,
        ];
    }
}
