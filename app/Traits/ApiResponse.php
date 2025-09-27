<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message = 'Error', int $code = 400, $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function resourceResponse(JsonResource $resource, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $resource->additional([
            'success' => true,
            'message' => $message,
        ])->response()->setStatusCode($code);
    }

    protected function collectionResponse(ResourceCollection $collection, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $collection->additional([
            'success' => true,
            'message' => $message,
        ])->response()->setStatusCode($code);
    }

    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    protected function unauthorizedResponse(string $message = 'Unauthorized access'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    protected function serviceUnavailableResponse(string $message = 'Service temporarily unavailable'): JsonResponse
    {
        return $this->errorResponse($message, 503);
    }

    protected function conflictResponse(string $message = 'Resource conflict'): JsonResponse
    {
        return $this->errorResponse($message, 409);
    }

    protected function createdResponse($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    protected function noContentResponse(string $message = 'No content'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }
}
