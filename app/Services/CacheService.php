<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Clear all application cache
     * This method clears ALL cache from the entire application including:
     * - All cached data (carousels, brands, models, listings, Odoo data, etc.)
     * - Application cache
     * - Route cache
     * - Config cache
     * - View cache
     *
     * @return array
     */
    public function clearAll(): array
    {
        $results = [
            'cache_data' => false,
            'route_cache' => false,
            'config_cache' => false,
            'view_cache' => false,
            'cleared_keys_count' => 0,
            'method' => 'unknown',
        ];

        try {
            // Clear application data cache
            $store = Cache::getStore();
            
            // For Redis: Clear all keys with the cache prefix
            if (method_exists($store, 'getRedis')) {
                $redis = $store->getRedis();
                if ($redis instanceof \Redis || $redis instanceof \Predis\Client) {
                    try {
                        $prefix = config('cache.prefix', config('database.redis.options.prefix', ''));
                        
                        // Get all keys matching the prefix
                        $pattern = $prefix . '*';
                        $keys = $redis->keys($pattern);
                        
                        if (!empty($keys)) {
                            // Delete all keys at once (more efficient)
                            foreach ($keys as $key) {
                                $redis->del($key);
                                $results['cleared_keys_count']++;
                            }
                            
                            $results['cache_data'] = true;
                            $results['method'] = 'redis_flush_all';
                            
                            Log::info('Cache cleared via Redis', [
                                'cleared_keys_count' => $results['cleared_keys_count'],
                                'pattern' => $pattern,
                            ]);
                        } else {
                            // If no keys found, try FLUSHDB (clears entire database)
                            if (method_exists($redis, 'flushDB')) {
                                $redis->flushDB();
                                $results['cache_data'] = true;
                                $results['method'] = 'redis_flushdb';
                                Log::info('Cache cleared via Redis FLUSHDB');
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('CacheService: Error clearing Redis cache', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Fallback: Use Laravel's flush method for other drivers (file, database, etc.)
            if (!$results['cache_data']) {
                Cache::flush();
                $results['cache_data'] = true;
                $results['method'] = 'laravel_flush';
                Log::info('Cache cleared via Laravel flush');
            }

            // Clear route cache
            try {
                if (file_exists(base_path('bootstrap/cache/routes-v7.php'))) {
                    unlink(base_path('bootstrap/cache/routes-v7.php'));
                    $results['route_cache'] = true;
                }
            } catch (\Exception $e) {
                Log::warning('CacheService: Could not clear route cache', [
                    'error' => $e->getMessage()
                ]);
            }

            // Clear config cache
            try {
                if (file_exists(base_path('bootstrap/cache/config.php'))) {
                    unlink(base_path('bootstrap/cache/config.php'));
                    $results['config_cache'] = true;
                }
            } catch (\Exception $e) {
                Log::warning('CacheService: Could not clear config cache', [
                    'error' => $e->getMessage()
                ]);
            }

            // Clear view cache
            try {
                $viewPath = config('view.compiled');
                if ($viewPath && is_dir($viewPath)) {
                    $files = glob($viewPath . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    $results['view_cache'] = true;
                }
            } catch (\Exception $e) {
                Log::warning('CacheService: Could not clear view cache', [
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('All cache cleared successfully', $results);

            return [
                'success' => true,
                'message' => 'All application cache cleared successfully',
                'data' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('CacheService: Error clearing all cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
                'data' => $results,
            ];
        }
    }

    /**
     * Verify Odoo API key
     *
     * @param string $providedKey
     * @return bool
     */
    public function verifyOdooApiKey(string $providedKey): bool
    {
        $odooApiKey = config('services.odoo.api_key');
        
        if (empty($odooApiKey)) {
            Log::error('CacheService: Odoo API key not configured');
            return false;
        }

        return $providedKey === $odooApiKey;
    }
}

