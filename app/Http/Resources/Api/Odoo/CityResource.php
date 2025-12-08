<?php

namespace App\Http\Resources\Api\Odoo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
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
            'name' => $name,
        ];
    }
}
