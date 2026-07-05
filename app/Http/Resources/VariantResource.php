<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price ? (float) $this->compare_at_price : null,
            // Omit cost_price in public APIs for margin security
            'inventory_quantity' => $this->inventory_quantity,
            'track_inventory' => (bool) $this->track_inventory,
            'continue_selling_out_of_stock' => (bool) $this->continue_selling_out_of_stock,
            'weight' => $this->weight ? (float) $this->weight : null,
            'weight_unit' => $this->weight_unit,
            'dimensions' => [
                'width' => $this->width ? (float) $this->width : null,
                'height' => $this->height ? (float) $this->height : null,
                'depth' => $this->depth ? (float) $this->depth : null,
                'unit' => $this->dimension_unit,
            ],
            'options' => $this->options,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
