<?php

namespace App\Http\Controllers\Api\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\DealerVerificationRequest;
use App\Http\Requests\Dealer\DealerApprovalRequest;
use App\Http\Requests\Dealer\OdooDealerApprovalRequest;
use App\Services\DealerVerificationService;
use App\Models\DealerApplication;
use App\Traits\ApiResponse;
use App\Traits\NotificationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DealerVerificationController extends Controller
{
    use ApiResponse, NotificationTrait;

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
            
            // Add partner_id from authenticated user (not from request for security)
            $data['partner_id'] = $user->partner_id ?? $user->id;
            
            $result = $this->dealerVerificationService->submitVerification($data, $user);

            // Send notification to user about dealer application submission
            if (isset($result['application_id'])) {
                $application = DealerApplication::find($result['application_id']);
                if ($application) {
                    $this->sendDealerApplicationNotification($application, 'submitted');
                }
            }

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

            // Send notification using sendNotification
            $application = DealerApplication::find($applicationId);
            if ($application) {
                $this->sendDealerApplicationNotification($application, 'approved');
            }

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

            // Send notification using sendNotification
            $application = DealerApplication::find($applicationId);
            if ($application) {
                $this->sendDealerApplicationNotification($application, 'rejected', $validated['reason'] ?? 'No reason provided');
            }

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
            // Load user relationship to ensure it's available
            $application = DealerApplication::with('user')->where('odoo_id', $odooId)->firstOrFail();
            
            if (!$application->user) {
                return $this->errorResponse(
                    'Dealer application does not have an associated user. partner_id: ' . $application->partner_id,
                    400
                );
            }
            
            $result = $this->dealerVerificationService->approveApplication(
                $application->id,
                $request->user()?->id
            );
            
            // Verify studio was created (service method already handles this, but double-check for response)
            $studio = \App\Models\DealerStudio::where('dealer_id', $application->user->id)
                ->where('is_active', true)
                ->first();
            
            $result['studio_created'] = $studio ? true : false;
            $result['studio_id'] = $studio?->id;
            $result['studio_name'] = $studio?->name;
            
            if (!$studio) {
                Log::warning('Studio verification failed after approval', [
                    'odoo_id' => $odooId,
                    'application_id' => $application->id,
                    'user_id' => $application->user->id,
                ]);
            }

            // Add approved_at timestamp to the result
            $result['approved_at'] = now()->toISOString();

            // Call Odoo mark-paid API after successful approval
            $this->callOdooMarkPaid($odooId);

            // Send notification using sendNotification
            $this->sendDealerApplicationNotification($application, 'approved');

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
    public function rejectByOdooId(Request $request, string $odooId)
    {
        try {
            $application = DealerApplication::where('odoo_id', $odooId)->firstOrFail();
            
            $reason = $request->input('reason') ?? 'Rejected via Odoo integration';
            
            $result = $this->dealerVerificationService->rejectApplication(
                $application->id,
                $reason,
                null // No specific reviewer for Odoo integration
            );

            // Call Odoo mark-paid API after successful rejection
            $this->callOdooMarkPaid($odooId);

            // Send notification using sendNotification
            $this->sendDealerApplicationNotification($application, 'rejected', $reason);

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
    public function processByOdooId(OdooDealerApprovalRequest $request, string $odooId)
    {
        try {
            $application = DealerApplication::where('odoo_id', $odooId)->firstOrFail();
            $validated = $request->validated();
            $action = $validated['action'];

            if ($action === 'approve') {
                $result = $this->dealerVerificationService->approveApplication($application->id, null);
            } else {
                $result = $this->dealerVerificationService->rejectApplication(
                    $application->id, 
                    'Rejected via Odoo integration', 
                    null
                );
            }

            // Call Odoo mark-paid API after successful processing
            $this->callOdooMarkPaid($odooId);

            return $this->successResponse($result, "Dealer application {$action}d successfully");

        } catch (Exception $e) {
            return $this->errorResponse(
                "Failed to {$validated['action']} dealer application: " . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send notification to user about dealer application status
     */
    private function sendDealerApplicationNotification(DealerApplication $application, string $status, string $reason = null)
    {
        try {
            $user = $application->user;
            if (!$user) {
                Log::warning('No user found for dealer application notification', [
                    'application_id' => $application->id
                ]);
                return;
            }

            // Get or create notification type based on status
            if ($status === 'submitted') {
                $notificationTypeName = 'dealer_application_submitted';
                $displayName = 'Dealer Application Submitted';
                $description = 'Your dealer application has been submitted';
                $icon = 'send';
                $color = '#3B82F6';
                $templateTitle = 'Dealer Application Submitted!';
                $templateBody = 'Your dealer application for {{company_name}} has been submitted and is under review.';
                $sortOrder = 13;
            } elseif ($status === 'approved') {
                $notificationTypeName = 'dealer_approved';
                $displayName = 'Dealer Application Approved';
                $description = 'Your dealer application has been approved';
                $icon = 'check_circle';
                $color = '#10B981';
                $templateTitle = 'Dealer Application Approved!';
                $templateBody = 'Congratulations! Your dealer application has been approved. You can now access dealer features.';
                $sortOrder = 14;
            } elseif ($status === 'rejected') {
                $notificationTypeName = 'dealer_rejected';
                $displayName = 'Dealer Application Rejected';
                $description = 'Your dealer application has been rejected';
                $icon = 'cancel';
                $color = '#EF4444';
                $templateTitle = 'Dealer Application Rejected';
                $templateBody = 'Your dealer application was not approved. Reason: {{reason}}';
                $sortOrder = 15;
            } else {
                $notificationTypeName = 'dealer_application_status';
                $displayName = 'Dealer Application Status';
                $description = 'Dealer application status update';
                $icon = 'info';
                $color = '#6B7280';
                $templateTitle = 'Dealer Application Update';
                $templateBody = 'Your dealer application status has been updated.';
                $sortOrder = 16;
            }

            $notificationType = \App\Models\NotificationType::firstOrCreate(
                ['name' => $notificationTypeName],
                [
                    'name' => $notificationTypeName,
                    'display_name' => $displayName,
                    'description' => $description,
                    'icon' => $icon,
                    'color' => $color,
                    'is_active' => true,
                    'default_template' => [
                        'title' => $templateTitle,
                        'body' => $templateBody
                    ],
                    'channels' => ['push'],
                    'sort_order' => $sortOrder
                ]
            );

            // Prepare notification data
            if ($status === 'submitted') {
                $title = 'Dealer Application Submitted!';
                $body = "Your dealer application for {$application->dealership_company_name} has been submitted and is under review. We'll notify you once it's been processed.";
            } elseif ($status === 'approved') {
                $title = 'Dealer Application Approved!';
                $body = "Congratulations! Your dealer application for {$application->dealership_company_name} has been approved. You can now access dealer features and create premium listings.";
            } elseif ($status === 'rejected') {
                $title = 'Dealer Application Rejected';
                $body = "Your dealer application for {$application->dealership_company_name} was not approved. Reason: " . ($reason ?? 'No reason provided');
            } else {
                $title = 'Dealer Application Update';
                $body = "Your dealer application status has been updated to: {$status}";
            }

            // Prepare notification data
            $notificationData = [
                'dealer_application_id' => $application->id,
                'company_name' => $application->dealership_company_name,
                'status' => $status,
                'reason' => $reason,
            ];

            if ($status === 'submitted') {
                $notificationData['submitted_at'] = now()->toISOString();
            } elseif ($status === 'approved') {
                $notificationData['approved_at'] = now()->toISOString();
            } elseif ($status === 'rejected') {
                $notificationData['rejected_at'] = now()->toISOString();
            }

            // Send notification to user
            $this->sendNotificationToUser(
                $user->id,
                $title,
                $body,
                $notificationType->id,
                $notificationData
            );

            Log::info('Dealer application notification sent', [
                'user_id' => $user->id,
                'application_id' => $application->id,
                'status' => $status,
                'company_name' => $application->dealership_company_name
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send dealer application notification', [
                'application_id' => $application->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
}
