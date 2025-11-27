<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreBudgetRequest;
use App\Http\Requests\Api\Odoo\UpdateBudgetRequest;
use App\Http\Resources\Api\Odoo\BudgetResource;
use App\Models\Budget;
use App\Services\Odoo\BudgetService;
use App\Traits\ApiResponse;

class BudgetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BudgetService $budgetService
    ) {}

    /**
     * Create a new budget from Odoo
     */
    public function store(StoreBudgetRequest $request)
    {
        $budget = $this->budgetService->create($request->validated());

        return $this->createdResponse(
            new BudgetResource($budget),
            'Budget created successfully'
        );
    }

    /**
     * Update budget by Odoo ID
     */
    public function updateByOdooId(UpdateBudgetRequest $request, string $odooId)
    {
        $budget = $this->budgetService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new BudgetResource($budget),
            'Budget updated successfully'
        );
    }

    /**
     * Delete budget by Odoo ID
     */
    public function deleteByOdooId(string $odooId)
    {
        $this->budgetService->deleteByOdooId($odooId);

        return $this->noContentResponse('Budget deleted successfully');
    }

    /**
     * Restore budget by Odoo ID
     */
    public function restoreByOdooId(string $odooId)
    {
        $budget = $this->budgetService->restoreByOdooId($odooId);

        return $this->successResponse(
            new BudgetResource($budget),
            'Budget restored successfully'
        );
    }

    /**
     * Get all budgets for data retrieval
     */
    public function getBudgets(\Illuminate\Http\Request $request)
    {
        try {
            // Build cache key based on request parameters
            $cacheKey = 'odoo_budgets_' . md5(serialize($request->only(['is_active'])));
            
            // Cache for 24 hours (86400 seconds)
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($request) {
                $query = Budget::query();

                // Filter by is_active if provided, otherwise default to active only
                if ($request->filled('is_active')) {
                    $query->where('is_active', $request->is_active);
                } else {
                    // Default behavior: only return active budgets
                    $query->where('is_active', true);
                }

                $budgets = $query->ordered()->get();
                
                return BudgetResource::collection($budgets)->resolve();
            });

            return $this->successResponse(
                $result,
                'Budgets retrieved successfully'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('BudgetController: Error fetching budgets', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to fetch budgets: ' . $e->getMessage(), 500);
        }
    }
}

