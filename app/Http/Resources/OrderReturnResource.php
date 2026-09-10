<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
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

            'order_id' => $this->order_id,

            'user' => $this->whenLoaded(
                'user',
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ]
            ),

            'total' => $this->total,

            'reason' => $this->reason,

            'return_date' => $this->return_date,

            'details' => OrderReturnDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'created_at' => $this->created_at,
        ];
    }
}
