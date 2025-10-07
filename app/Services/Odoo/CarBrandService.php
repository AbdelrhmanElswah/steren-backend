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

        if (!empty($data['logo'])) {
            $this->handleLogo($brand, (string) $data['logo']);
        }

        return $brand;
    }

    public function updateByOdooId(string $odooId, array $data): CarBrand
    {
        $brand = parent::updateByOdooId($odooId, $data);

        if (array_key_exists('logo', $data)) {
            $brand->media()?->delete();

            if (!empty($data['logo'])) {
                $this->handleLogo($brand, (string) $data['logo']);
            }
        }

        return $brand;
    }

    private function handleLogo(CarBrand $brand, string $base64Logo): void
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
