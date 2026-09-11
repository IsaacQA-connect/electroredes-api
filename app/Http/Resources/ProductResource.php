<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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

            'code' => $this->code,

            'barcode' => $this->barcode,

            'name' => $this->name,

            'description' => $this->description,

            'unit' => $this->unit,

            'cost' => $this->cost,

            'sale_price' => $this->sale_price,

            'stock' => $this->stock,

            'minimum_stock' => $this->minimum_stock,

            'low_stock' => $this->stock <= $this->minimum_stock,

            'status' => $this->status,

            'category_id' => $this->category_id,

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
