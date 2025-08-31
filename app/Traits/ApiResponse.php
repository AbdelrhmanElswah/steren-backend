<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    /**
     * Success response
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error response
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Resource response
     */
    protected function resourceResponse(JsonResource $resource, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $resource->additional([
            'success' => true,
            'message' => $message,
        ])->response()->setStatusCode($code);
    }

    /**
     * Collection response
     */
    protected function collectionResponse(ResourceCollection $collection, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $collection->additional([
            'success' => true,
            'message' => $message,
        ])->response()->setStatusCode($code);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized access'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }
}
