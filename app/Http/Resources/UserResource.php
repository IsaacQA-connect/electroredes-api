<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'email'  => $this->email,
            'status' => $this->status,

            'role' => $this->whenLoaded('role', function () {
                return [
                    'id'   => $this->role->id,
                    'name' => $this->role->name,
                ];
            }),

            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id'              => $this->customer->id,
                    'name'            => $this->customer->name,
                    'document_type'   => $this->customer->document_type,
                    'document_number' => $this->customer->document_number,
                    'phone'           => $this->customer->phone,
                    'address'         => $this->customer->address,
                ];
            }),
        ];
    }
}