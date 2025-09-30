<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OdooRequestTracking
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = $request->header('X-Request-ID', uniqid());
        
        Log::info('Odoo API Request Started', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'input' => $request->all(),
            'timestamp' => now()->toISOString(),
        ]);

        $response = $next($request);

        $duration = microtime(true) - $startTime;
        
        Log::info('Odoo API Request Completed', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration * 1000,
            'response_size' => strlen($response->getContent()),
            'timestamp' => now()->toISOString(),
        ]);

        if ($duration > 5.0) {
            Log::warning('Slow Odoo API Request', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'duration_ms' => $duration * 1000,
                'timestamp' => now()->toISOString(),
            ]);
        }

        return $response;
    }
}
