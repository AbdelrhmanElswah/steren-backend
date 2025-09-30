<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdooDatabaseException extends Exception
{
    protected $statusCode = 500;
    protected $errorCode = 'ODOO_DATABASE_ERROR';
    protected $operation = '';
    protected $context = [];

    public function __construct(string $message = 'Odoo database operation failed', string $operation = '', array $context = [], int $code = 0, ?Exception $previous = null)
    {
        $this->operation = $operation;
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'status_code' => $this->statusCode,
                'operation' => $this->operation,
                'context' => $this->context,
                'timestamp' => now()->toISOString(),
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ],
        ], $this->statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
