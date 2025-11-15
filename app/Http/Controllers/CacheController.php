<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CacheController extends Controller
{
    use ApiResponse;

    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Clear all application cache
     * Requires Odoo API key for authentication
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        // Get secret key from request (can be in header or body)
        $secretKey = $request->header('X-Odoo-API-Key') 
            ?? $request->header('Authorization') 
            ?? $request->input('api_key')
            ?? $request->input('secret_key');
        
        // Remove 'Bearer ' prefix if present
        if ($secretKey && str_starts_with($secretKey, 'Bearer ')) {
            $secretKey = substr($secretKey, 7);
        }
        
        // Verify secret key
        if (empty($secretKey) || !$this->cacheService->verifyOdooApiKey($secretKey)) {
            Log::warning('Cache clear: Invalid or missing API key', [
                'ip_address' => $request->ip(),
                'has_key' => !empty($secretKey),
            ]);

            return $this->errorResponse('Invalid or missing API key', 401);
        }

        try {
            $result = $this->cacheService->clearAll();

            // Check if cache clearing was successful
            if (!$result['success']) {
                Log::warning('Cache clear failed', [
                    'ip_address' => $request->ip(),
                    'message' => $result['message'] ?? 'Unknown error',
                ]);

                return $this->errorResponse(
                    $result['message'] ?? 'Failed to clear cache',
                    500
                );
            }

            $data = $result['data'] ?? [];
            Log::info('Cache cleared by Odoo', [
                'ip_address' => $request->ip(),
                'method' => $data['method'] ?? 'unknown',
                'cleared_keys_count' => $data['cleared_keys_count'] ?? 0,
                'cache_data' => $data['cache_data'] ?? false,
                'route_cache' => $data['route_cache'] ?? false,
                'config_cache' => $data['config_cache'] ?? false,
                'view_cache' => $data['view_cache'] ?? false,
            ]);

            return $this->successResponse($data, $result['message']);

        } catch (\Exception $e) {
            Log::error('Cache clear error', [
                'ip_address' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to clear cache: ' . $e->getMessage(),
                500
            );
        }
    }
}

