<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

class ErrorTrackingService
{
    public function trackError(Exception $exception, Request $request, array $context = []): void
    {
        $errorData = [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => $request->headers->all(),
                'input' => $request->all(),
            ],
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'request_id' => $request->header('X-Request-ID', uniqid()),
        ];

        Log::error('Odoo API Error', $errorData);

        if (method_exists($exception, 'getErrorCode')) {
            Log::error('Odoo Error Code: ' . $exception->getErrorCode(), $errorData);
        }

        if (method_exists($exception, 'getContext')) {
            Log::error('Odoo Error Context', $exception->getContext());
        }
    }

    public function trackValidationError(array $errors, Request $request, array $context = []): void
    {
        $errorData = [
            'type' => 'validation_error',
            'errors' => $errors,
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'input' => $request->all(),
            ],
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'request_id' => $request->header('X-Request-ID', uniqid()),
        ];

        Log::warning('Odoo Validation Error', $errorData);
    }

    public function trackApiCall(string $endpoint, string $method, array $data = [], array $response = [], float $duration = 0): void
    {
        $logData = [
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => $data,
            'response' => $response,
            'duration_ms' => $duration * 1000,
            'timestamp' => now()->toISOString(),
        ];

        Log::info('Odoo API Call', $logData);
    }

    public function trackPerformance(string $operation, float $duration, array $context = []): void
    {
        $logData = [
            'operation' => $operation,
            'duration_ms' => $duration * 1000,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ];

        if ($duration > 5.0) {
            Log::warning('Slow Odoo Operation', $logData);
        } else {
            Log::info('Odoo Operation Performance', $logData);
        }
    }
}
