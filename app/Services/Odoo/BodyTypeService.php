<?php

namespace App\Services\Odoo;

use App\Models\BodyType;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BodyTypeService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new BodyType());
    }

    public function create(array $data): BodyType
    {
        $bodyType = parent::create($data);

        if (!empty($data['image'])) {
            $this->handleImage($bodyType, $data['image']);
        }

        return $bodyType;
    }

    public function updateByOdooId(string $odooId, array $data): BodyType
    {
        $bodyType = parent::updateByOdooId($odooId, $data);

        if (array_key_exists('image', $data)) {
            $bodyType->media()?->delete();

            if (!empty($data['image'])) {
                $this->handleImage($bodyType, $data['image']);
            }
        }

        return $bodyType;
    }

    private function handleImage(BodyType $bodyType, string $imageData): void
    {
        // Decode base64 image data
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $decodedImage = base64_decode($imageData);
        
        if ($decodedImage === false) {
            throw new \Exception('Invalid base64 image data');
        }
        
        // Generate unique filename
        $filename = 'bodytype_' . $bodyType->id . '_' . Str::random(10) . '.png';
        $path = 'bodytypes/' . $filename;
        
        // Store the image
        Storage::disk('public')->put($path, $decodedImage);
        
        // Create media record
        Media::create([
            'modelable_type' => BodyType::class,
            'modelable_id' => $bodyType->id,
            'media' => $imageData,
            'path_file_name' => $path,
            'file_type' => 'image/png',
            'info' => 'icon',
        ]);
    }
}
