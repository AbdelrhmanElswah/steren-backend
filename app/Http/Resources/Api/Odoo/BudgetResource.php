<?php

namespace App\Http\Resources\Api\Odoo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get language from request
        $lang = $request->query('lang');
        if (!in_array($lang, ['ar', 'kr'])) {
            $lang = null;
        }
        
        // Get localized name
        $name = $lang ? ($this->{'name_' . $lang} ?: $this->name) : $this->name;
        
        return [
            'id' => $this->id,
            'odoo_id' => $this->odoo_id,
            'name' => $name,
            'value' => $this->value,
            'formatted_value' => $this->formatted_value,
            'sequence' => $this->sequence,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

