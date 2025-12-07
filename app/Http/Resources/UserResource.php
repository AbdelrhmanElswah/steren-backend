<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_verified' => !is_null($this->phone_verified_at),
            'birthday' => $this->birthday?->format('Y-m-d'),
            'profile_picture' => $this->profile_picture,
            'profile_picture_media' => $this->profilePictureMedia?->media,
            'language' => $this->language,
            'theme' => $this->theme,
            // Note: dealer_application removed - use dealer_status.application instead
        ];
    }
}


