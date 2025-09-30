<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdooValidationException extends Exception
{
    protected $statusCode = 422;
    protected $errorCode = 'ODOO_VALIDATION_ERROR';
    protected $errors = [];
    protected $context = [];

    public function __construct(string $message = 'Odoo validation failed', array $errors = [], array $context = [], int $code = 0, ?Exception $previous = null)
    {
        $this->errors = $errors;
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
                'errors' => $this->errors,
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

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
