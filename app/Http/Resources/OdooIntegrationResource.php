<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OdooIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->resource['success'] ?? false,
            'partner_id' => $this->resource['partner_id'] ?? null,
            'message' => $this->resource['message'] ?? null,
            'errors' => $this->resource['errors'] ?? [],
        ];
    }
}
