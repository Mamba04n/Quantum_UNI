<?php

namespace App\Http\Resources\Api\V1\Integration;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ItemResource extends JsonResource
{
    /**
     * Transforma el recurso a array whitelist estricto para integración con CMMS.
     */
    public function toArray(Request $request): array
    {
        $category = null;
        if (isset($this->technical_category)) {
            $category = is_object($this->technical_category) && enum_exists($this->technical_category::class)
                ? $this->technical_category->value
                : (string) $this->technical_category;
        } elseif (isset($this->type)) {
            $category = is_object($this->type) && enum_exists($this->type::class)
                ? $this->type->value
                : (string) $this->type;
        }

        return [
            'id' => (string) $this->id,
            'sku' => (string) ($this->sku ?? ''),
            'part_number' => (string) ($this->part_number ?? ''),
            'name' => (string) $this->name,
            'description' => (string) ($this->description ?? ''),
            'category' => $category ?: 'general',
            'unit' => 'UND',
            'active' => (bool) $this->active,
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'version' => (int) ($this->updated_at?->timestamp ?? 1),
        ];
    }
}
