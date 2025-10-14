<?php

namespace App\Exceptions;

use Exception;

class VehicleDatabasesException extends Exception
{
    protected $statusCode;

    public function __construct(string $message = '', int $statusCode = 500, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
