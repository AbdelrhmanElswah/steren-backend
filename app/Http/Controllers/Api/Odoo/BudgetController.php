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
    public function getBudgets()
    {
        $budgets = Budget::active()->ordered()->get();

        return $this->successResponse(
            BudgetResource::collection($budgets),
            'Budgets retrieved successfully'
        );
    }
}

