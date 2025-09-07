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
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'phone' => $this->formatted_phone,
            'profile_picture' => $this->profile_picture,
            'language' => $this->language,
            'theme' => $this->theme,
            'is_verified' => (bool) $this->is_verified,
        ];
    }
}


