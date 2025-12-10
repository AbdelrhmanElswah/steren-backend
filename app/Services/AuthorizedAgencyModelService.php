<?php

namespace App\Services;

use App\Models\AuthorizedAgencyModel;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthorizedAgencyModelService
{
    private const OUTER_INFO = 'agency_model_outer';
    private const INNER_INFO = 'agency_model_inner';

    public function create(
        array $data,
        ?UploadedFile $outerImage = null,
        ?string $outerImageBase64 = null,
        array $innerImages = [],
        array $innerImagesBase64 = []
    ): AuthorizedAgencyModel {
        $agencyModel = AuthorizedAgencyModel::create($data);

        $this->replaceOuterImage($agencyModel, $outerImage, $outerImageBase64);
        $this->replaceInnerImages($agencyModel, $innerImages, $innerImagesBase64);

        return $agencyModel->fresh(['outerImage', 'innerImages']);
    }

    public function update(
        AuthorizedAgencyModel $agencyModel,
        array $data,
        ?UploadedFile $outerImage = null,
        ?string $outerImageBase64 = null,
        array $innerImages = [],
        array $innerImagesBase64 = []
    ): AuthorizedAgencyModel {
        $agencyModel->update($data);

        if ($outerImage || $outerImageBase64 !== null) {
            $this->replaceOuterImage($agencyModel, $outerImage, $outerImageBase64);
        }

        if (!empty($innerImages) || !empty($innerImagesBase64)) {
            $this->replaceInnerImages($agencyModel, $innerImages, $innerImagesBase64);
        }

        return $agencyModel->fresh(['outerImage', 'innerImages']);
    }

    private function replaceOuterImage(
        AuthorizedAgencyModel $agencyModel,
        ?UploadedFile $file = null,
        ?string $base64 = null
    ): void {
        if ($agencyModel->outerImage) {
            $agencyModel->outerImage->delete();
        }

        if ($file) {
            $this->storeMediaFromFile($agencyModel, $file, self::OUTER_INFO, 0);
            return;
        }

        if ($base64) {
            $this->storeMediaFromBase64($agencyModel, $base64, self::OUTER_INFO, 0);
        }
    }

    private function replaceInnerImages(
        AuthorizedAgencyModel $agencyModel,
        array $files = [],
        array $base64Images = []
    ): void {
        if ($agencyModel->innerImages()->exists()) {
            $agencyModel->innerImages->each->delete();
        }

        $order = 0;

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $this->storeMediaFromFile($agencyModel, $file, self::INNER_INFO, $order++);
            }
        }

        foreach ($base64Images as $base64) {
            if (!empty($base64)) {
                $this->storeMediaFromBase64($agencyModel, $base64, self::INNER_INFO, $order++);
            }
        }
    }

    private function storeMediaFromFile(
        AuthorizedAgencyModel $agencyModel,
        UploadedFile $file,
        string $info,
        ?int $order = null
    ): void {
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

        $filename = 'agency_model_' . $agencyModel->id . '_' . Str::random(10) . '.' . $ext;
        $directory = $info === self::OUTER_INFO ? 'outer' : 'inner';
        $path = 'authorized_agency_models/' . $directory . '/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        $this->createMediaRecord($agencyModel, $path, $mime, $info, $order);
    }

    private function storeMediaFromBase64(
        AuthorizedAgencyModel $agencyModel,
        string $base64Media,
        string $info,
        ?int $order = null
    ): void {
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

        $filename = 'agency_model_' . $agencyModel->id . '_' . Str::random(10) . '.' . $ext;
        $directory = $info === self::OUTER_INFO ? 'outer' : 'inner';
        $path = 'authorized_agency_models/' . $directory . '/' . $filename;

        Storage::disk('s3')->put($path, $binary);

        $this->createMediaRecord($agencyModel, $path, $mime, $info, $order);
    }

    private function createMediaRecord(
        AuthorizedAgencyModel $agencyModel,
        string $path,
        string $mime,
        string $info,
        ?int $order
    ): void {
        Media::create([
            'modelable_type' => AuthorizedAgencyModel::class,
            'modelable_id' => $agencyModel->id,
            'media' => $path,
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => $info,
            'order' => $order,
        ]);
    }
}


