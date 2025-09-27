<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'full_name' => $this->resource['full_name'] ?? null,
            'phone' => $this->resource['phone'] ?? null,
            'email' => $this->resource['email'] ?? null,
        ];
    }
}
