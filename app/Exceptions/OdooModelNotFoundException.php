<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdooModelNotFoundException extends Exception
{
    protected $statusCode = 404;
    protected $errorCode = 'ODOO_MODEL_NOT_FOUND';
    protected $modelType = '';
    protected $identifier = '';
    protected $context = [];

    public function __construct(string $message = 'Odoo model not found', string $modelType = '', string $identifier = '', array $context = [], int $code = 0, ?Exception $previous = null)
    {
        $this->modelType = $modelType;
        $this->identifier = $identifier;
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
                'model_type' => $this->modelType,
                'identifier' => $this->identifier,
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

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
