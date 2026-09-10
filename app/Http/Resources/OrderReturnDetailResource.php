<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnDetailResource extends JsonResource
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

            'order_detail_id' => $this->order_detail_id,

            'product' => $this->when(
                $this->relationLoaded('orderDetail')
                && $this->orderDetail->relationLoaded('product'),
                fn () => [
                    'id' => $this->orderDetail->product->id,
                    'name' => $this->orderDetail->product->name,
                    'code' => $this->orderDetail->product->code,
                ]
            ),

            'quantity' => $this->quantity,

            'unit_price' => $this->unit_price,

            'subtotal' => $this->subtotal,

            'created_at' => $this->created_at,
        ];
    }
}
