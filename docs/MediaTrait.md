# MediaTrait Documentation

## Overview
The `MediaTrait` provides functionality to generate temporary S3 URLs for media files in Laravel resources. This is useful for serving files from S3 with time-limited access.

## Usage

### 1. Basic Setup
Add the trait to your resource class:

```php
<?php

namespace App\Http\Resources;

use App\Traits\MediaTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class YourResource extends JsonResource
{
    use MediaTrait;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'media' => $this->when($this->relationLoaded('media') && $this->media, 
                $this->getMainMediaWithTemporaryUrl($this->media)
            ),
            'medias' => $this->when($this->relationLoaded('medias') && $this->medias, 
                $this->getMediasWithTemporaryUrls($this->medias)
            ),
        ];
    }
}
```

### 2. Available Methods

#### `getTemporaryUrl(?string $path, int $expirationMinutes = 60): ?string`
Generates a temporary S3 URL for a single file path.

```php
$temporaryUrl = $this->getTemporaryUrl($media->file_path, 120); // 2 hours
```

#### `getMainMediaWithTemporaryUrl($media, int $expirationMinutes = 60): ?array`
Returns main media with temporary URL.

```php
$mediaData = $this->getMainMediaWithTemporaryUrl($this->media, 60);
```

#### `getMediasWithTemporaryUrls($medias, int $expirationMinutes = 60): array`
Returns all medias with temporary URLs.

```php
$mediasData = $this->getMediasWithTemporaryUrls($this->medias, 60);
```

### 3. Model Requirements

Your model should have the following relationships:

```php
// For single media
public function media()
{
    return $this->morphOne(Media::class, 'modelable')->where('info', 'main');
}

// For multiple medias
public function medias()
{
    return $this->morphMany(Media::class, 'modelable');
}
```

### 4. Controller Usage

Make sure to eager load the media relationships:

```php
public function index()
{
    $items = YourModel::with(['media', 'medias'])->get();
    return YourResource::collection($items);
}
```

### 5. Response Format

The trait will return media data in this format:

```json
{
    "id": 1,
    "name": "Example",
    "media": {
        "id": 1,
        "file_path": "brands/brand_1_abarth_1234567890.png",
        "file_name": "brand_1_abarth_1234567890.png",
        "file_size": 12345,
        "mime_type": "image/png",
        "info": "logo",
        "temporary_url": "https://s3.amazonaws.com/bucket/brands/brand_1_abarth_1234567890.png?X-Amz-Algorithm=...",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "medias": [
        {
            "id": 1,
            "file_path": "brands/brand_1_abarth_1234567890.png",
            "file_name": "brand_1_abarth_1234567890.png",
            "file_size": 12345,
            "mime_type": "image/png",
            "info": "logo",
            "temporary_url": "https://s3.amazonaws.com/bucket/brands/brand_1_abarth_1234567890.png?X-Amz-Algorithm=...",
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    ]
}
```

### 6. Configuration

Make sure your S3 configuration is set up in `config/filesystems.php`:

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
],
```

### 7. Error Handling

The trait includes error handling and logging. If a file doesn't exist or there's an S3 error, it will:
- Return `null` for single media
- Return an empty array for multiple medias
- Log the error for debugging

### 8. Security Notes

- Temporary URLs expire after the specified time (default 60 minutes)
- URLs are signed and cannot be tampered with
- Files are not publicly accessible without the temporary URL
- Consider the expiration time based on your use case (shorter for sensitive files, longer for public content)
