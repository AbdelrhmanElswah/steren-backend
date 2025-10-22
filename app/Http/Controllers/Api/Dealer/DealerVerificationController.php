<?php

namespace App\Http\Controllers\Api\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\DealerVerificationRequest;
use App\Http\Requests\Dealer\DealerApprovalRequest;
use App\Services\DealerVerificationService;
use App\Models\DealerApplication;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DealerVerificationController extends Controller
{
    use ApiResponse;

    protected DealerVerificationService $dealerVerificationService;

    public function __construct(DealerVerificationService $dealerVerificationService)
    {
        $this->dealerVerificationService = $dealerVerificationService;
    }

    /**
     * Submit dealer verification to Odoo
     */
    public function submit(DealerVerificationRequest $request)
    {
        try {
            $data = $request->validated();
            $user = $request->user();
            
            $result = $this->dealerVerificationService->submitVerification($data, $user);

            $message = $result['synced_with_odoo'] ?? false 
                ? 'Dealer verification submitted and synced successfully'
                : 'Application saved. Sync with Odoo pending.';

            return $this->successResponse($result, $message, 201);

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to save dealer application: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update verification status (for Odoo webhook)
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'application_id' => 'required|integer|exists:dealer_applications,id',
            'status' => 'required|string|in:approved,rejected,under_review',
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $result = $this->dealerVerificationService->updateVerificationStatus(
                $request->application_id,
                $request->status,
                $request->reason
            );

            return $this->successResponse($result, 'Verification status updated successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to update verification status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get verification status for a user
     */
    public function status(Request $request)
    {
        $user = $request->user();
        
        $latestApplication = $user->latestDealerApplication;
        $activeApplication = $user->activeDealerApplication;

        if (!$latestApplication) {
            return $this->successResponse([
                'has_application' => false,
                'is_dealer' => $user->is_dealer,
                'status' => null,
                'message' => 'No dealer verification found'
            ]);
        }

        $response = [
            'has_application' => true,
            'is_dealer' => $user->is_dealer,
            'application' => [
                'id' => $latestApplication->id,
                'status' => $latestApplication->status,
                'status_badge_color' => $latestApplication->status_badge_color,
                'seller_type' => $latestApplication->seller_type,
                'dealership_company_name' => $latestApplication->dealership_company_name,
                'subscription_plan' => $latestApplication->subscriptionPlan ? [
                    'id' => $latestApplication->subscriptionPlan->id,
                    'name' => $latestApplication->subscriptionPlan->name,
                    'price' => $latestApplication->subscriptionPlan->price,
                ] : null,
                'payment_completed' => $latestApplication->payment_completed,
                'created_at' => $latestApplication->created_at,
                'reviewed_at' => $latestApplication->reviewed_at,
                'approved_at' => $latestApplication->approved_at,
                'expires_at' => $latestApplication->expires_at,
                'rejection_reason' => $latestApplication->rejection_reason,
                'days_until_expiration' => $latestApplication->days_until_expiration,
            ],
            'is_active' => $activeApplication ? $activeApplication->isActive() : false,
            'is_expiring_soon' => $activeApplication ? $activeApplication->isExpiringSoon() : false,
            'is_expired' => $latestApplication->isExpired(),
        ];

        switch ($latestApplication->status) {
            case 'pending':
                $response['message'] = 'Your verification is pending review';
                break;
            case 'under_review':
                $response['message'] = 'Your verification is under review';
                break;
            case 'approved':
                if ($latestApplication->payment_completed) {
                    if ($activeApplication && $activeApplication->isActive()) {
                        $response['message'] = 'You are now a verified dealer';
                    } elseif ($latestApplication->isExpired()) {
                        $response['message'] = 'Your subscription has expired';
                    } elseif ($activeApplication && $activeApplication->isExpiringSoon()) {
                        $response['message'] = 'Your subscription expires in ' . $latestApplication->days_until_expiration . ' days';
                    }
                } else {
                    $response['message'] = 'Your verification has been approved. Please complete payment to activate your dealer account';
                }
                break;
            case 'rejected':
                $response['message'] = 'Your verification was not approved';
                break;
        }

        return $this->successResponse($response);
    }

    /**
     * Approve a dealer application
     */
    public function approve(Request $request, int $applicationId)
    {
        try {
            $result = $this->dealerVerificationService->approveApplication(
                $applicationId,
                $request->user()?->id
            );

            return $this->successResponse($result, 'Dealer application approved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to approve dealer application: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Reject a dealer application
     */
    public function reject(DealerApprovalRequest $request, int $applicationId)
    {
        try {
            $validated = $request->validated();
            
            $result = $this->dealerVerificationService->rejectApplication(
                $applicationId,
                $validated['reason'] ?? 'No reason provided',
                $request->user()?->id
            );

            return $this->successResponse($result, 'Dealer application rejected successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to reject dealer application: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Approve or reject a dealer application (unified endpoint)
     */
    public function processApplication(DealerApprovalRequest $request, int $applicationId)
    {
        try {
            $validated = $request->validated();
            $action = $validated['action'];
            $reason = $validated['reason'] ?? null;
            $reviewedBy = $request->user()?->id;

            if ($action === 'approve') {
                $result = $this->dealerVerificationService->approveApplication($applicationId, $reviewedBy);
            } else {
                $result = $this->dealerVerificationService->rejectApplication(
                    $applicationId, 
                    $reason ?? 'No reason provided', 
                    $reviewedBy
                );
            }

            return $this->successResponse($result, "Dealer application {$action}d successfully");

        } catch (Exception $e) {
            return $this->errorResponse(
                "Failed to {$validated['action']} dealer application: " . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Approve a dealer application by Odoo ID
     */
    public function approveByOdooId(Request $request, string $odooId)
    {
        try {
            $application = DealerApplication::where('odoo_id', $odooId)->firstOrFail();
            
            $result = $this->dealerVerificationService->approveApplication(
                $application->id,
                $request->user()?->id
            );

            // Call Odoo mark-paid API after successful approval
            $this->callOdooMarkPaid($odooId);

            return $this->successResponse($result, 'Dealer application approved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to approve dealer application: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Call Odoo mark-paid API
     */
    private function callOdooMarkPaid(string $odooId)
    {
        try {
            $odooApiUrl = config('services.odoo.api_url');
            $odooApiKey = config('services.odoo.api_key');
            
            if (!$odooApiUrl || !$odooApiKey) {
                Log::warning('Odoo API configuration missing for mark-paid call', [
                    'odoo_id' => $odooId
                ]);
                return;
            }

            $markPaidUrl = rtrim($odooApiUrl, '/') . "/api/verification/{$odooId}/mark-paid";
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $odooApiKey,
                ])
                ->post($markPaidUrl);

            if ($response->successful()) {
                Log::info('Odoo mark-paid API called successfully', [
                    'odoo_id' => $odooId,
                    'url' => $markPaidUrl,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
            } else {
                Log::error('Odoo mark-paid API call failed', [
                    'odoo_id' => $odooId,
                    'url' => $markPaidUrl,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to call Odoo mark-paid API', [
                'odoo_id' => $odooId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Reject a dealer application by Odoo ID
     */
    public function rejectByOdooId(DealerApprovalRequest $request, string $odooId)
    {
        try {
            $application = DealerApplication::where('odoo_id', $odooId)->firstOrFail();
            $validated = $request->validated();
            
            $result = $this->dealerVerificationService->rejectApplication(
                $application->id,
                $validated['reason'] ?? 'No reason provided',
                $request->user()?->id
            );

            return $this->successResponse($result, 'Dealer application rejected successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to reject dealer application: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Process a dealer application by Odoo ID (unified endpoint)
     */
    public function processByOdooId(DealerApprovalRequest $request, string $odooId)
    {
        try {
            $application = DealerApplication::where('odoo_id', $odooId)->firstOrFail();
            $validated = $request->validated();
            $action = $validated['action'];
            $reason = $validated['reason'] ?? null;
            $reviewedBy = $request->user()?->id;

            if ($action === 'approve') {
                $result = $this->dealerVerificationService->approveApplication($application->id, $reviewedBy);
            } else {
                $result = $this->dealerVerificationService->rejectApplication(
                    $application->id, 
                    $reason ?? 'No reason provided', 
                    $reviewedBy
                );
            }

            return $this->successResponse($result, "Dealer application {$action}d successfully");

        } catch (Exception $e) {
            return $this->errorResponse(
                "Failed to {$validated['action']} dealer application: " . $e->getMessage(),
                500
            );
        }
    }
}
