<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreSubscriptionPlanRequest;
use App\Http\Requests\Api\Odoo\UpdateSubscriptionPlanRequest;
use App\Http\Requests\Api\Odoo\GetSubscriptionPlansRequest;
use App\Http\Resources\Api\Odoo\SubscriptionPlanResource;
use App\Services\Odoo\SubscriptionPlanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SubscriptionPlanController extends Controller
{
    use ApiResponse;

    public function __construct(
        private SubscriptionPlanService $subscriptionPlanService
    ) {}

    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        try {
            $subscriptionPlan = $this->subscriptionPlanService->createFromOdoo($request->validated());

            return $this->createdResponse(
                new SubscriptionPlanResource($subscriptionPlan),
                'Subscription plan created successfully'
            );
        } catch (\Exception $e) {
            Log::error('SubscriptionPlanController: Error creating subscription plan', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create subscription plan: ' . $e->getMessage(), 500);
        }
    }

    public function updateByOdooId(UpdateSubscriptionPlanRequest $request, string $odooId): JsonResponse
    {
        try {
            $subscriptionPlan = $this->subscriptionPlanService->updateFromOdoo($odooId, $request->validated());

            return $this->successResponse(
                new SubscriptionPlanResource($subscriptionPlan),
                'Subscription plan updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('SubscriptionPlanController: Error updating subscription plan', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update subscription plan: ' . $e->getMessage(), 500);
        }
    }

    public function deleteByOdooId(string $odooId): JsonResponse
    {
        try {
            $this->subscriptionPlanService->deleteByOdooId($odooId);

            return $this->noContentResponse('Subscription plan deleted successfully');
        } catch (\Exception $e) {
            Log::error('SubscriptionPlanController: Error deleting subscription plan', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to delete subscription plan: ' . $e->getMessage(), 500);
        }
    }

    public function restoreByOdooId(string $odooId): JsonResponse
    {
        try {
            $subscriptionPlan = $this->subscriptionPlanService->restoreByOdooId($odooId);

            return $this->successResponse(
                new SubscriptionPlanResource($subscriptionPlan),
                'Subscription plan restored successfully'
            );
        } catch (\Exception $e) {
            Log::error('SubscriptionPlanController: Error restoring subscription plan', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to restore subscription plan: ' . $e->getMessage(), 500);
        }
    }

    public function getSubscriptionPlans(GetSubscriptionPlansRequest $request): JsonResponse
    {
        try {
            $plans = \App\Models\SubscriptionPlan::query()
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%')
                          ->orWhere('description', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('id'), function ($query) use ($request) {
                    $query->where('id', $request->id);
                })
                ->when($request->filled('odoo_id'), function ($query) use ($request) {
                    $query->where('odoo_id', $request->odoo_id);
                })
                ->when($request->filled('is_active'), function ($query) use ($request) {
                    $query->where('is_active', $request->is_active);
                })
                ->when($request->filled('is_popular'), function ($query) use ($request) {
                    $query->where('is_popular', $request->is_popular);
                })
                ->when($request->filled('is_featured'), function ($query) use ($request) {
                    $query->where('is_featured', $request->is_featured);
                })
                ->when($request->filled('billing_cycle'), function ($query) use ($request) {
                    $query->where('billing_cycle', $request->billing_cycle);
                })
                ->ordered()
                ->get();

            $collection = SubscriptionPlanResource::collection($plans);
            return $collection->additional([
                'success' => true,
                'message' => 'Subscription plans retrieved successfully',
                'total' => $plans->count()
            ])->response();

        } catch (\Exception $e) {
            Log::error('SubscriptionPlanController: Error fetching subscription plans', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to fetch subscription plans: ' . $e->getMessage(), 500);
        }
    }
}

