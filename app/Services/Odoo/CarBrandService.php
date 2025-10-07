<?php

namespace App\Services\Odoo;

use App\Models\CarBrand;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarBrandService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarBrand());
    }

    public function create(array $data): CarBrand
    {
        $brand = parent::create($data);

        if (!empty($data['media'])) {
            $this->handleLogo($brand, $data['media']);
        }

        return $brand;
    }

    public function updateByOdooId(string $odooId, array $data): CarBrand
    {
        $brand = parent::updateByOdooId($odooId, $data);

        if (array_key_exists('media', $data)) {
            $brand->media()?->delete();

            if (!empty($data['media'])) {
                $this->handleLogo($brand, $data['media']);
            }
        }

        return $brand;
    }

    /**
     * Handle logo - accepts both file uploads and base64 strings
     * 
     * @param CarBrand $brand
     * @param mixed $logo Can be either an UploadedFile or a base64 string
     * @return void
     */
    private function handleLogo(CarBrand $brand, $logo): void
    {
        // Check if it's a file upload
        if ($logo instanceof \Illuminate\Http\UploadedFile) {
            $this->handleLogoFromFile($brand, $logo);
        } 
        // Otherwise treat as base64 string
        elseif (is_string($logo)) {
            $this->handleLogoFromBase64($brand, $logo);
        } else {
            throw new \RuntimeException('Invalid logo data type');
        }
    }

    /**
     * Handle logo from uploaded file
     * 
     * @param CarBrand $brand
     * @param \Illuminate\Http\UploadedFile $file
     * @return void
     */
    private function handleLogoFromFile(CarBrand $brand, \Illuminate\Http\UploadedFile $file): void
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

        $filename = 'brand_' . $brand->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'car_brands/' . $filename;

        // Store the file directly
        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        Media::create([
            'modelable_type' => CarBrand::class,
            'modelable_id' => $brand->id,
            'media' => 'odoo',
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => 'logo',
        ]);
    }

    /**
     * Handle logo from base64 string
     * 
     * @param CarBrand $brand
     * @param string $base64Logo
     * @return void
     */
    private function handleLogoFromBase64(CarBrand $brand, string $base64Logo): void
    {
        $clean = trim($base64Logo);
        if (str_starts_with($clean, '+')) {
            $clean = urldecode($clean);
        }
        if (str_contains($clean, ',')) {
            $clean = substr($clean, strpos($clean, ',') + 1);
        }
        $clean = str_replace(' ', '+', $clean);

        $binary = base64_decode($clean);
        if ($binary === false) {
            throw new \RuntimeException('Invalid base64 logo data');
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

        $filename = 'brand_' . $brand->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'car_brands/' . $filename;

        Storage::disk('s3')->put($path, $binary);

        Media::create([
            'modelable_type' => CarBrand::class,
            'modelable_id' => $brand->id,
            'media' => 'odoo',
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => 'logo',
        ]);
    }
}
