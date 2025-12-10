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
        array $innerImagesBase64 = [],
        ?string $existingOuterImageUrl = null,
        array $existingInnerImageUrls = []
    ): AuthorizedAgencyModel {
        $agencyModel->update($data);

        $this->syncOuterImage(
            $agencyModel,
            $existingOuterImageUrl,
            $outerImage,
            $outerImageBase64
        );

        $this->syncInnerImages(
            $agencyModel,
            $existingInnerImageUrls,
            $innerImages,
            $innerImagesBase64
        );

        return $agencyModel->fresh(['outerImage', 'innerImages']);
    }

    private function syncOuterImage(
        AuthorizedAgencyModel $agencyModel,
        ?string $existingOuterImageUrl = null,
        ?UploadedFile $file = null,
        ?string $base64 = null
    ): void {
        $current = $agencyModel->outerImage;

        // If client wants to keep the current outer image (url matches), do nothing.
        if ($existingOuterImageUrl && $current && $this->mediaUrlMatches($current, $existingOuterImageUrl)) {
            // no-op
        } else {
            // Not kept -> delete current and replace if new provided
            if ($current) {
                $current->delete();
            }

            if ($file) {
                $this->storeMediaFromFile($agencyModel, $file, self::OUTER_INFO, 0);
                return;
            }

            if ($base64) {
                $this->storeMediaFromBase64($agencyModel, $base64, self::OUTER_INFO, 0);
            }
        }
    }

    private function syncInnerImages(
        AuthorizedAgencyModel $agencyModel,
        array $existingInnerImageUrls = [],
        array $files = [],
        array $base64Images = []
    ): void {
        $existingUrls = collect($existingInnerImageUrls)->filter()->values();
        $current = $agencyModel->innerImages()->get();

        // Delete any current inner images not present in existing urls
        foreach ($current as $img) {
            if (!$this->mediaUrlInList($img, $existingUrls)) {
                $img->delete();
            }
        }

        $order = 0;

        // Keep order of existing images as provided
        foreach ($existingUrls as $url) {
            $img = $current->first(fn ($item) => $this->mediaUrlMatches($item, $url));
            if ($img) {
                $img->order = $order++;
                $img->save();
            }
        }

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

    private function mediaUrlMatches(Media $media, string $url): bool
    {
        $publicUrl = $media->getS3PublicUrl($media->path_file_name);
        $tempUrl = $media->getS3TemporaryUrl($media->path_file_name);

        return $url === $publicUrl || $url === $tempUrl;
    }

    private function mediaUrlInList(Media $media, $urlList): bool
    {
        $publicUrl = $media->getS3PublicUrl($media->path_file_name);
        $tempUrl = $media->getS3TemporaryUrl($media->path_file_name);

        return collect($urlList)->contains(function ($url) use ($publicUrl, $tempUrl) {
            return $url === $publicUrl || $url === $tempUrl;
        });
    }
}
