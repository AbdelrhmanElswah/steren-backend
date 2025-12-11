<?php

namespace App\Http\Resources\Api;

use App\Traits\ResourceMediaTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorizedAgencyModelResource extends JsonResource
{
    use ResourceMediaTrait;

    public function toArray(Request $request): array
    {
        $useTemporaryUrl = $this->shouldUseTemporaryUrls($request);
        $expirationMinutes = $this->getExpirationMinutes($request);

        return [
            'id' => $this->id,
            'authorized_agency_id' => $this->authorized_agency_id,
            'car_model_id' => $this->car_model_id,
            'gcc_import' => $this->gcc_import,
            'engine_size' => $this->engine_size,
            'forced_induction' => $this->forced_induction,
            'fuel_type' => $this->fuel_type,
            'cylinders' => $this->cylinders,
            'transmission' => $this->transmission,
            'seating_capacity' => $this->seating_capacity,
            'drive_type' => $this->drive_type,
            'price_start_from' => $this->price_start_from,
            'price_currency' => $this->price_currency,
            'horse_power' => $this->horse_power,
            'length_from' => $this->length_from,
            'length_to' => $this->length_to,
            'width_from' => $this->width_from,
            'width_to' => $this->width_to,
            'height_from' => $this->height_from,
            'height_to' => $this->height_to,
            'torque' => $this->torque,
            'acceleration' => $this->acceleration,
            'top_speed' => $this->top_speed,
            'outer_image' => $this->when(
                $this->relationLoaded('outerImage') && $this->outerImage,
                $this->getStandardMedia($this->outerImage, $request, $useTemporaryUrl, $expirationMinutes)
            ),
            'inner_images' => $this->when(
                $this->relationLoaded('innerImages'),
                $this->getMultipleMediaData($this->innerImages, $request, $useTemporaryUrl, $expirationMinutes)
            ),
        ];
    }
}


