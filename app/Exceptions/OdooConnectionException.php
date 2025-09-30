<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OdooConnectionException extends Exception
{
    protected $statusCode = 503;
    protected $errorCode = 'ODOO_CONNECTION_ERROR';

    public function __construct(string $message = 'Unable to connect to Odoo service', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        $errorData = [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'status_code' => $this->statusCode,
                'timestamp' => now()->toISOString(),
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'endpoint' => $request->fullUrl(),
                'method' => $request->method(),
            ],
        ];

        Log::error('Odoo Connection Error', [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'request' => $request->all(),
            'url' => $request->fullUrl(),
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json($errorData, $this->statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
