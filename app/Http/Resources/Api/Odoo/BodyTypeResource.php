<?php

namespace App\Http\Resources\Api\Odoo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ResourceMediaTrait;
use Carbon\CarbonInterface;
use Carbon\Carbon;

class BodyTypeResource extends JsonResource
{
    use ResourceMediaTrait;

    public function toArray(Request $request): array
    {
        $useTemporaryUrl = $this->shouldUseTemporaryUrls($request);
        $expirationMinutes = $this->getExpirationMinutes($request);

        return [
            'id' => $this->id,
            'odoo_id' => $this->odoo_id,
            'name' => $this->name,
            'media' => $this->when(
                $this->relationLoaded('medias') && $this->medias->isNotEmpty(),
                $this->getStandardMedia($this->medias->first(), $request, $useTemporaryUrl, $expirationMinutes)
            ),
            'created_at' => $this->formatTimestamp($this->created_at),
            'updated_at' => $this->formatTimestamp($this->updated_at),
            'deleted_at' => $this->formatTimestamp($this->deleted_at),
        ];
    }

    private function formatTimestamp($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        try {
            return Carbon::parse($value)->toISOString();
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}