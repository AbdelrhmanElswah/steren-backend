<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdooPartnerCreationException extends Exception
{
    protected $statusCode = 422;
    protected $errorCode = 'ODOO_PARTNER_CREATION_ERROR';
    protected $errors = [];

    public function __construct(string $message = 'Failed to create partner in Odoo', array $errors = [], int $code = 0, ?Exception $previous = null)
    {
        $this->errors = $errors;
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
}
