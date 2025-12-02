<?php

namespace App\Services\Odoo;

use App\Models\Carousel;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
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

        if (!empty($data['media'])) {
            $this->handleCarouselImage($carousel, $data['media']);
        }

        // Clear all carousel cache after creation
        $this->clearCarouselCache();

        return $carousel;
    }

    public function updateByOdooId(string $odooId, array $data): Carousel
    {
        $carousel = parent::updateByOdooId($odooId, $data);

        if (array_key_exists('media', $data)) {
            // Delete existing media (MediaObserver will handle S3 file deletion)
            $carousel->load('medias');
            $carousel->medias()->delete();

            if (!empty($data['media'])) {
                $this->handleCarouselImage($carousel, $data['media']);
            }
        }

        // Clear all carousel cache after update
        $this->clearCarouselCache();

        return $carousel;
    }

    public function deleteByOdooId(string $odooId): bool
    {
        $result = parent::deleteByOdooId($odooId);
        
        // Clear all carousel cache after deletion
        $this->clearCarouselCache();
        
        return $result;
    }

    public function restoreByOdooId(string $odooId): Carousel
    {
        $carousel = parent::restoreByOdooId($odooId);
        
        // Clear all carousel cache after restore
        $this->clearCarouselCache();
        
        return $carousel;
    }

    /**
     * Clear all carousel-related cache
     * This includes:
     * - carousels_active_* (with limit or 'all')
     * - carousels_all_* (with md5 hash)
     * - carousel_* (individual carousel cache)
     */
    protected function clearCarouselCache(): void
    {
        try {
            // Try to clear cache by pattern if Redis is available
            $store = Cache::getStore();
            if (method_exists($store, 'getRedis')) {
                $redis = $store->getRedis();
                if ($redis instanceof \Redis || $redis instanceof \Predis\Client) {
                    $pattern = '*carousel*';
                    $prefix = config('cache.prefix', config('database.redis.options.prefix', ''));
                    
                    // Get all keys matching the pattern
                    $keys = $redis->keys($prefix . $pattern);
                    
                    if (!empty($keys)) {
                        // Remove prefix from keys
                        if ($prefix) {
                            $keys = array_map(function($key) use ($prefix) {
                                return str_replace($prefix, '', $key);
                            }, $keys);
                        }
                        
                        // Delete all matching keys
                        foreach ($keys as $key) {
                            Cache::forget($key);
                        }
                        
                        return;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fall back to manual cache clearing if pattern matching fails
            \Illuminate\Support\Facades\Log::warning('CarouselService: Could not clear cache by pattern', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Clear common cache keys manually (fallback)
        $this->clearCommonCarouselCacheKeys();
    }

    /**
     * Clear common carousel cache keys manually
     * Used as fallback when pattern matching is not available
     */
    protected function clearCommonCarouselCacheKeys(): void
    {
        // Clear active carousels cache (common variations)
        Cache::forget('carousels_active_all');
        Cache::forget('carousels_active_10');
        Cache::forget('carousels_active_20');
        Cache::forget('carousels_active_50');
        
        // Clear all carousels cache (common filter combinations)
        // Note: Since the key includes md5 hash, we can't predict all variations
        // But we can clear common ones
        $commonFilters = [
            ['active_only' => false, 'search' => null],
            ['active_only' => true, 'search' => null],
            ['active_only' => false],
            ['active_only' => true],
        ];
        
        foreach ($commonFilters as $filters) {
            $cacheKey = 'carousels_all_' . md5(serialize($filters));
            Cache::forget($cacheKey);
        }
        
        // Clear individual carousel cache for all existing carousels
        // This is less efficient but ensures cache is cleared
        Carousel::select('odoo_id')->chunk(100, function ($carousels) {
            foreach ($carousels as $carousel) {
                Cache::forget('carousel_' . $carousel->odoo_id);
            }
        });
    }

    /**
     * Handle carousel image - accepts both file uploads and base64 strings
     * 
     * @param Carousel $carousel
     * @param mixed $image Can be either an UploadedFile or a base64 string
     * @return void
     */
    private function handleCarouselImage(Carousel $carousel, $image): void
    {
        // Check if it's a file upload
        if ($image instanceof \Illuminate\Http\UploadedFile) {
            $this->handleImageFromFile($carousel, $image);
        } 
        // Otherwise treat as base64 string
        elseif (is_string($image)) {
            $this->handleImageFromBase64($carousel, $image);
        } else {
            throw new \RuntimeException('Invalid image data type');
        }
    }

    /**
     * Handle image from uploaded file
     * 
     * @param Carousel $carousel
     * @param \Illuminate\Http\UploadedFile $file
     * @return void
     */
    private function handleImageFromFile(Carousel $carousel, \Illuminate\Http\UploadedFile $file): void
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

        $filename = 'carousel_' . $carousel->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'carousels/' . $filename;

        // Store the file directly
        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        Media::create([
            'modelable_type' => Carousel::class,
            'modelable_id' => $carousel->id,
            'media' => $path,
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => 'main',
        ]);
    }

    /**
     * Handle image from base64 string
     * 
     * @param Carousel $carousel
     * @param string $base64Image
     * @return void
     */
    private function handleImageFromBase64(Carousel $carousel, string $base64Image): void
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

        $filename = 'carousel_' . $carousel->id . '_' . Str::random(10) . '.' . $ext;
        $path = 'carousels/' . $filename;

        Storage::disk('s3')->put($path, $binary);

        Media::create([
            'modelable_type' => Carousel::class,
            'modelable_id' => $carousel->id,
            'media' => $path,
            'path_file_name' => $path,
            'file_type' => $mime,
            'info' => 'main',
        ]);
    }
}
