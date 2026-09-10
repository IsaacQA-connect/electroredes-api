<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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

            'channel' => $this->channel,

            'status' => $this->status?->value,

            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'document_type' => $this->customer->document_type,
                    'document_number' => $this->customer->document_number,
                    'phone' => $this->customer->phone,
                ];
            }),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,

            'order_date' => $this->order_date,

            'notes' => $this->notes,

            'details' => OrderDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'payments' => PaymentResource::collection(
                $this->whenLoaded('payments')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

    }
}
