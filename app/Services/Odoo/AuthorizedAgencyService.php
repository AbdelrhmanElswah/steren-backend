<?php

namespace App\Services\Odoo;

use App\Models\AuthorizedAgency;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthorizedAgencyService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new AuthorizedAgency());
    }

    public function create(array $data): AuthorizedAgency
    {
        $agency = parent::create($data);

        if (!empty($data['media'])) {
            $this->handleMedia($agency, $data['media']);
        }

        return $agency;
    }

    public function updateByOdooId(string $odooId, array $data): AuthorizedAgency
    {
        $agency = parent::updateByOdooId($odooId, $data);

        if (array_key_exists('media', $data)) {
            $agency->media()?->delete();

            if (!empty($data['media'])) {
                $this->handleMedia($agency, $data['media']);
            }
        }

        return $agency;
    }

    private function handleMedia(AuthorizedAgency $agency, $media): void
    {
        if ($media instanceof \Illuminate\Http\UploadedFile) {
            $this->storeMediaFromFile($agency, $media);
        } elseif (is_string($media)) {
            $this->storeMediaFromBase64($agency, $media);
        } else {
            throw new \RuntimeException('Invalid media data type');
        }
    }

    private function storeMediaFromFile(AuthorizedAgency $agency, \Illuminate\Http\UploadedFile $file): void
    {
        $ext = $file->getClientOriginalExtension();
        $mime = $file->getMimeType();

        if (empty($ext)) {
            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/bmp' => 'bmp',
                'image/webp' => 'webp',
                default => 'png',
            };
        }

        $filename = 'authorized_agency_' . $agency->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'authorized_agencies/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        $this->createMediaRecord($agency, $path, $mime);
    }

    private function storeMediaFromBase64(AuthorizedAgency $agency, string $base64Media): void
    {
        $clean = trim($base64Media);
        if (str_starts_with($clean, '+')) {
            $clean = urldecode($clean);
        }
        if (str_contains($clean, ',')) {
            $clean = substr($clean, strpos($clean, ',') + 1);
        }
        $clean = str_replace(' ', '+', $clean);

        $binary = base64_decode($clean);
        if ($binary === false) {
            throw new \RuntimeException('Invalid base64 media data');
        }

        $mime = 'image/png';
        $ext = 'png';
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            $mime = 'image/jpeg';
            $ext = 'jpg';
        } elseif (str_starts_with($binary, "\x89PNG")) {
            $mime = 'image/png';
            $ext = 'png';
        } elseif (str_starts_with($binary, 'GIF8')) {
            $mime = 'image/gif';
            $ext = 'gif';
        } elseif (str_starts_with($binary, "BM")) {
            $mime = 'image/bmp';
            $ext = 'bmp';
        } elseif (str_starts_with($binary, 'RIFF')) {
            $mime = 'image/webp';
            $ext = 'webp';
        }

        $filename = 'authorized_agency_' . $agency->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'authorized_agencies/' . $filename;

        Storage::disk('s3')->put($path, $binary);

        $this->createMediaRecord($agency, $path, $mime);
    }

    private function createMediaRecord(AuthorizedAgency $agency, string $path, string $mime): void
    {
        Media::create([
            'modelable_type' => AuthorizedAgency::class,
            'modelable_id' => $agency->id,
            'media' => $path,
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => 'authorized_agency_media',
        ]);
    }
}

