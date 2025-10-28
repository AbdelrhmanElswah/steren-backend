<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesFileUpload
{

    public function uploadFile(UploadedFile $file, $relatedModel, $directory = 'documents') {

        $targetDir = storage_path('app/public/' . $directory);

        if (!File::isDirectory($targetDir)) {

            File::makeDirectory($targetDir, 0755, true);
        }
        $filename = time() . '.' . $file->getClientOriginalExtension();

        $file->move($targetDir, $filename);

        $relativePath = 'storage/' . $directory . '/' . $filename;

        $mappedType = $this->mapMimeTypeToEnum($file->getClientMimeType());

        return [
            'file_name' => $filename,
            'file_extension' => $file->getClientOriginalExtension(),
            'path' => $relativePath, // Store the relative path
            'file_type' => $mappedType,
            'file' => $filename,
            'modelable_id' => $relatedModel->id,
            'modelable_type' => get_class($relatedModel)
        ];
    }

    public function uploadFileToS3(UploadedFile $file, $relatedModel, $directory = 'documents') {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        // Upload to S3
        $path = Storage::disk('s3')->putFileAs(
            $directory,
            $file,
            $filename,
            'public' // Make the file publicly accessible
        );

        if (!$path) {
            throw new \Exception('Failed to upload file to S3');
        }

        // Get the full URL using the AWS URL from config
        $url = config('filesystems.disks.s3.endpoint') . '/' . $path;


        $mappedType = $this->mapMimeTypeToEnum($file->getClientMimeType());

        return [
            'file_name' => $filename,
            'file_extension' => $file->getClientOriginalExtension(),
            'path' => $path, // Store the S3 path
            'url' => $url, // Store the full URL
            'file_type' => $mappedType,
            'file' => $filename,
            'modelable_id' => $relatedModel->id,
            'modelable_type' => get_class($relatedModel)
        ];
    }

    public function mapMimeTypeToEnum($mimeType) {
        $map = [
            'application/pdf' => 'pdf',
            'application/msword' => 'word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'word',
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
            'text/plain' => 'text',
            // Image types
            'image/jpeg' => 'image',
            'image/jpg' => 'image',
            'image/png' => 'image',
            'image/gif' => 'image',
            'image/webp' => 'image',
            'image/svg+xml' => 'image',
        ];

        return $map[$mimeType] ?? 'other';
    }


    public function getFileFromS3($path) {
        $file = Storage::disk('s3')->temporaryUrl(
            $path, now()->addMinutes(1)
        );
        return $file;
    }
}
