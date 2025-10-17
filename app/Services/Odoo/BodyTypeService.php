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

        if (!empty($data['media'])) {
            $this->handleImage($bodyType, $data['media']);
        }

        return $bodyType;
    }

    public function updateByOdooId(string $odooId, array $data): BodyType
    {
        $bodyType = parent::updateByOdooId($odooId, $data);

        if (array_key_exists('media', $data)) {
            $bodyType->media()?->delete();

            if (!empty($data['media'])) {
                $this->handleImage($bodyType, $data['media']);
            }
        }

        return $bodyType;
    }

    /**
     * Handle body type image - accepts both file uploads and base64 strings
     * 
     * @param BodyType $bodyType
     * @param mixed $image Can be either an UploadedFile or a base64 string
     * @return void
     */
    private function handleImage(BodyType $bodyType, $image): void
    {
        // Check if it's a file upload
        if ($image instanceof \Illuminate\Http\UploadedFile) {
            $this->handleImageFromFile($bodyType, $image);
        } 
        // Otherwise treat as base64 string
        elseif (is_string($image)) {
            $this->handleImageFromBase64($bodyType, $image);
        } else {
            throw new \RuntimeException('Invalid image data type');
        }
    }

    /**
     * Handle image from uploaded file
     * 
     * @param BodyType $bodyType
     * @param \Illuminate\Http\UploadedFile $file
     * @return void
     */
    private function handleImageFromFile(BodyType $bodyType, \Illuminate\Http\UploadedFile $file): void
    {
        $ext = $file->getClientOriginalExtension();
        $mime = $file->getMimeType();
        
        // Fallback to detecting from mime type if extension is not available
        if (empty($ext)) {
            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/bmp' => 'bmp',
                'image/webp' => 'webp',
                default => 'png'
            };
        }

        $filename = 'bodytype_' . $bodyType->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'bodytypes/' . $filename;

        // Store the file directly
        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        Media::create([
            'modelable_type' => BodyType::class,
            'modelable_id' => $bodyType->id,
            'media' => $path,
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => 'icon',
        ]);
    }

    /**
     * Handle image from base64 string
     * 
     * @param BodyType $bodyType
     * @param string $base64Image
     * @return void
     */
    private function handleImageFromBase64(BodyType $bodyType, string $base64Image): void
    {
        $clean = trim($base64Image);
        if (str_starts_with($clean, '+')) {
            $clean = urldecode($clean);
        }
        if (str_contains($clean, ',')) {
            $clean = substr($clean, strpos($clean, ',') + 1);
        }
        $clean = str_replace(' ', '+', $clean);

        $binary = base64_decode($clean);
        if ($binary === false) {
            throw new \RuntimeException('Invalid base64 image data');
        }

        $ext = 'png';
        $mime = 'image/png';
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            $ext = 'jpg';
            $mime = 'image/jpeg';
        } elseif (str_starts_with($binary, "\x89PNG")) {
            $ext = 'png';
            $mime = 'image/png';
        } elseif (str_starts_with($binary, 'GIF8')) {
            $ext = 'gif';
            $mime = 'image/gif';
        } elseif (str_starts_with($binary, "BM")) {
            $ext = 'bmp';
            $mime = 'image/bmp';
        } elseif (str_starts_with($binary, 'RIFF')) {
            $ext = 'webp';
            $mime = 'image/webp';
        }

        $filename = 'bodytype_' . $bodyType->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'bodytypes/' . $filename;

        Storage::disk('s3')->put($path, $binary);

        Media::create([
            'modelable_type' => BodyType::class,
            'modelable_id' => $bodyType->id,
            'media' => $path,
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => 'icon',
        ]);
    }
}
