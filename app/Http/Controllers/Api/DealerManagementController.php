<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DealerManagementService;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DealerManagementController extends Controller
{
    use ApiResponse;

    protected DealerManagementService $dealerService;

    public function __construct(DealerManagementService $dealerService)
    {
        $this->dealerService = $dealerService;
    }

    /**
     * Check if current user is a dealer
     */
    public function checkDealerStatus(): JsonResponse
    {
        try {
            $user = Auth::user();
            $isDealer = $this->dealerService->isDealer($user);

            return $this->successResponse([
                'is_dealer' => $isDealer,
                'user_id' => $user->id,
                'email' => $user->email,
                'dealer_status' => $user->is_dealer ? 'active' : 'inactive'
            ], 'Dealer status retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to check dealer status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Make current user a dealer
     */
    public function makeDealer(): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($this->dealerService->isDealer($user)) {
                return $this->errorResponse(
                    'User is already a dealer',
                    400
                );
            }

            $success = $this->dealerService->makeUserDealer($user);

            if ($success) {
                return $this->successResponse([
                    'is_dealer' => true,
                    'user_id' => $user->id,
                    'message' => 'User successfully marked as dealer'
                ], 'User is now a dealer');
            } else {
                return $this->errorResponse(
                    'Failed to make user a dealer',
                    500
                );
            }

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to make user dealer: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove dealer status from current user
     */
    public function removeDealerStatus(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$this->dealerService->isDealer($user)) {
                return $this->errorResponse(
                    'User is not a dealer',
                    400
                );
            }

            $success = $this->dealerService->removeDealerStatus($user);

            if ($success) {
                return $this->successResponse([
                    'is_dealer' => false,
                    'user_id' => $user->id,
                    'message' => 'Dealer status removed successfully'
                ], 'User is no longer a dealer');
            } else {
                return $this->errorResponse(
                    'Failed to remove dealer status',
                    500
                );
            }

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to remove dealer status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get dealer statistics for current user
     */
    public function getDealerStatistics(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$this->dealerService->isDealer($user)) {
                return $this->errorResponse(
                    'User is not a dealer',
                    400
                );
            }

            $statistics = $this->dealerService->getDealerStatistics($user);

            return $this->successResponse([
                'statistics' => $statistics,
                'user_id' => $user->id
            ], 'Dealer statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to get dealer statistics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Admin: Get all dealers
     */
    public function getAllDealers(): JsonResponse
    {
        try {
            $dealers = $this->dealerService->getAllDealers();

            return $this->successResponse([
                'dealers' => $dealers,
                'count' => $dealers->count()
            ], 'All dealers retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to get dealers: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Admin: Make specific user a dealer
     */
    public function makeUserDealer(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()
                );
            }

            $user = User::findOrFail($request->user_id);

            if ($this->dealerService->isDealer($user)) {
                return $this->errorResponse(
                    'User is already a dealer',
                    400
                );
            }

            $success = $this->dealerService->makeUserDealer($user);

            if ($success) {
                return $this->successResponse([
                    'is_dealer' => true,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'message' => 'User successfully marked as dealer'
                ], 'User is now a dealer');
            } else {
                return $this->errorResponse(
                    'Failed to make user a dealer',
                    500
                );
            }

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to make user dealer: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Admin: Remove dealer status from specific user
     */
    public function removeUserDealerStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()
                );
            }

            $user = User::findOrFail($request->user_id);

            if (!$this->dealerService->isDealer($user)) {
                return $this->errorResponse(
                    'User is not a dealer',
                    400
                );
            }

            $success = $this->dealerService->removeDealerStatus($user);

            if ($success) {
                return $this->successResponse([
                    'is_dealer' => false,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'message' => 'Dealer status removed successfully'
                ], 'User is no longer a dealer');
            } else {
                return $this->errorResponse(
                    'Failed to remove dealer status',
                    500
                );
            }

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to remove dealer status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Admin: Bulk make users dealers
     */
    public function bulkMakeDealers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()
                );
            }

            $results = $this->dealerService->bulkMakeDealers($request->user_ids);

            return $this->successResponse([
                'results' => $results,
                'summary' => [
                    'total' => count($request->user_ids),
                    'successful' => count($results['success']),
                    'failed' => count($results['failed'])
                ]
            ], 'Bulk dealer assignment completed');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to bulk make dealers: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Admin: Bulk remove dealer status
     */
    public function bulkRemoveDealerStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()
                );
            }

            $results = $this->dealerService->bulkRemoveDealerStatus($request->user_ids);

            return $this->successResponse([
                'results' => $results,
                'summary' => [
                    'total' => count($request->user_ids),
                    'successful' => count($results['success']),
                    'failed' => count($results['failed'])
                ]
            ], 'Bulk dealer status removal completed');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to bulk remove dealer status: ' . $e->getMessage(),
                500
            );
        }
    }
}
