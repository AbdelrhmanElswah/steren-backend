<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheController extends Controller
{
    use ApiResponse;

    /**
     * Clear all application cache
     *
     * @return JsonResponse
     */
    public function clearAll(): JsonResponse
    {
        try {
            $clearedKeys = [];
            $patterns = [
                '*carousel*',
                '*brand*',
                '*car_model*',
                '*similar_cars*',
                '*search_brands*',
                '*brand_suggestions*',
                '*odoo_brands*',
                '*odoo_car_models*',
                '*carousels*',
                '*car_brand*',
                '*car_listing*',
                '*listing*',
            ];

            // Try to clear cache by pattern if Redis is available
            $store = Cache::getStore();
            if (method_exists($store, 'getRedis')) {
                $redis = $store->getRedis();
                if ($redis instanceof \Redis || $redis instanceof \Predis\Client) {
                    $prefix = config('cache.prefix', config('database.redis.options.prefix', ''));

                    foreach ($patterns as $pattern) {
                        try {
                            // Get all keys matching the pattern
                            $keys = $redis->keys($prefix . $pattern);

                            if (!empty($keys)) {
                                // Remove prefix from keys
                                $keysToDelete = $keys;
                                if ($prefix) {
                                    $keysToDelete = array_map(function($key) use ($prefix) {
                                        return str_replace($prefix, '', $key);
                                    }, $keys);
                                }

                                // Delete all matching keys
                                foreach ($keysToDelete as $key) {
                                    Cache::forget($key);
                                    $clearedKeys[] = $key;
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('CacheController: Error clearing cache pattern', [
                                'pattern' => $pattern,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    Log::info('Cache cleared', [
                        'user_id' => auth()->id(),
                        'user_email' => auth()->user()->email ?? null,
                        'cleared_keys_count' => count($clearedKeys),
                        'patterns' => $patterns,
                    ]);

                    return $this->successResponse([
                        'message' => 'All cache cleared successfully',
                        'cleared_keys_count' => count($clearedKeys),
                        'patterns' => $patterns,
                    ], 'Cache cleared successfully');
                }
            }

            // Fallback: Clear all cache if using file/database driver
            Cache::flush();

            Log::info('Cache flushed (fallback method)', [
                'user_id' => auth()->id(),
                'user_email' => auth()->user()->email ?? null,
                'method' => 'flush',
            ]);

            return $this->successResponse([
                'message' => 'All cache cleared successfully (using flush method)',
                'method' => 'flush',
            ], 'Cache cleared successfully');

        } catch (\Exception $e) {
            Log::error('CacheController: Error clearing cache', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to clear cache: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Clear specific cache patterns
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function clearPattern(): JsonResponse
    {
        try {
            $request = request();
            $patterns = $request->input('patterns', []);

            if (empty($patterns) || !is_array($patterns)) {
                return $this->errorResponse('Patterns array is required', 400);
            }

            $clearedKeys = [];
            $store = Cache::getStore();

            if (method_exists($store, 'getRedis')) {
                $redis = $store->getRedis();
                if ($redis instanceof \Redis || $redis instanceof \Predis\Client) {
                    $prefix = config('cache.prefix', config('database.redis.options.prefix', ''));

                    foreach ($patterns as $pattern) {
                        try {
                            $keys = $redis->keys($prefix . $pattern);

                            if (!empty($keys)) {
                                $keysToDelete = $keys;
                                if ($prefix) {
                                    $keysToDelete = array_map(function($key) use ($prefix) {
                                        return str_replace($prefix, '', $key);
                                    }, $keys);
                                }

                                foreach ($keysToDelete as $key) {
                                    Cache::forget($key);
                                    $clearedKeys[] = $key;
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('CacheController: Error clearing cache pattern', [
                                'pattern' => $pattern,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    Log::info('Specific cache patterns cleared', [
                        'user_id' => auth()->id(),
                        'patterns' => $patterns,
                        'cleared_keys_count' => count($clearedKeys),
                    ]);

                    return $this->successResponse([
                        'message' => 'Cache patterns cleared successfully',
                        'patterns' => $patterns,
                        'cleared_keys_count' => count($clearedKeys),
                    ], 'Cache patterns cleared successfully');
                }
            }

            return $this->errorResponse('Pattern-based cache clearing is only available with Redis cache driver', 400);

        } catch (\Exception $e) {
            Log::error('CacheController: Error clearing cache patterns', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to clear cache patterns: ' . $e->getMessage(),
                500
            );
        }
    }
}

