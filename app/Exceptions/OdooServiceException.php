<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdooServiceException extends Exception
{
    protected $statusCode = 500;
    protected $errorCode = 'ODOO_SERVICE_ERROR';
    protected $serviceName = '';
    protected $operation = '';
    protected $context = [];

    public function __construct(string $message = 'Odoo service operation failed', string $serviceName = '', string $operation = '', array $context = [], int $code = 0, ?Exception $previous = null)
    {
        $this->serviceName = $serviceName;
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
                'service_name' => $this->serviceName,
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

    public function getServiceName(): string
    {
        return $this->serviceName;
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
