<?php

namespace App\Exceptions;

use Exception;

class StandingTechException extends Exception
{
    protected $statusCode;

    public function __construct(string $message = "", int $statusCode = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error' => 'StandingTech API Error',
        ], $this->statusCode);
    }
}

