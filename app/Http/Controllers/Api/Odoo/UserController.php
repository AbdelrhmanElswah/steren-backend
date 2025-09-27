<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreUserRequest;
use App\Http\Requests\Api\Odoo\UpdateUserRequest;
use App\Http\Resources\Api\Odoo\UserResource;
use App\Services\Odoo\UserService;
use App\Traits\ApiResponse;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private UserService $UserService
    ) {}

    public function store(StoreUserRequest $request)
    {
        $User = $this->UserService->create($request->validated());

        return $this->createdResponse(
            new UserResource($User),
            'User created successfully'
        );
    }public function updateByOdooId(UpdateUserRequest $request, string $odooId)
    {
        $User = $this->UserService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new UserResource($User),
            'User updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->UserService->deleteByOdooId($odooId);

        return $this->noContentResponse('User deleted successfully');
    }public function restoreByOdooId(string $odooId)
    {
        $User = $this->UserService->restoreByOdooId($odooId);

        return $this->successResponse(
            new UserResource($User),
            'User restored successfully'
        );
    }
}