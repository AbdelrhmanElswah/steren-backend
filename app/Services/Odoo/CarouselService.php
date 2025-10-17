<?php

namespace App\Services\Odoo;

use App\Models\Carousel;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarouselService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new Carousel());
    }

    public function create(array $data): Carousel
    {
        $carousel = parent::create($data);

        if (!empty($data['img'])) {
            $this->handleCarouselImage($carousel, $data['img']);
        }

        return $carousel;
    }

    public function updateByOdooId(string $odooId, array $data): Carousel
    {
        $carousel = parent::updateByOdooId($odooId, $data);

        if (array_key_exists('img', $data)) {
            $carousel->medias()?->delete();

            if (!empty($data['img'])) {
                $this->handleCarouselImage($carousel, $data['img']);
            }
        }

        return $carousel;
    }

    private function handleCarouselImage(Carousel $carousel, string $base64Image): void
    {
        // Clean the base64 string - remove any URL encoding and whitespace
        $cleanBase64 = trim($base64Image);
        
        // If it starts with +, it's URL-encoded base64, decode it first
        if (str_starts_with($cleanBase64, '+')) {
            $cleanBase64 = urldecode($cleanBase64);
        }
        
        // Remove any data URL prefix if present (data:image/png;base64,)
        if (str_contains($cleanBase64, ',')) {
            $cleanBase64 = substr($cleanBase64, strpos($cleanBase64, ',') + 1);
        }

        // Decode base64 image
        $imageData = base64_decode($cleanBase64);
        
        if ($imageData === false) {
            throw new \Exception('Failed to decode base64 image');
        }

        // Detect image type from the decoded data
        $imageType = 'png'; // default
        if (str_starts_with($imageData, "\xFF\xD8\xFF")) {
            $imageType = 'jpg';
        } elseif (str_starts_with($imageData, "\x89PNG")) {
            $imageType = 'png';
        } elseif (str_starts_with($imageData, "GIF8")) {
            $imageType = 'gif';
        }

        // Generate filename with correct extension
        $filename = 'carousel_' . $carousel->id . '_' . time() . '.' . $imageType;
        $path = 'carousels/' . $filename;

        // Store image
        Storage::disk('public')->put($path, $imageData);

        // Create media record
        Media::create([
            'modelable_type' => Carousel::class,
            'modelable_id' => $carousel->id,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => 'image/' . $imageType,
            'file_size' => strlen($imageData),
            'info' => 'main',
            'alt_text' => $carousel->title,
        ]);
    }
}
