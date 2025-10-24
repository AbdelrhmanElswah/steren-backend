<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\ListingApprovalRequest;
use App\Services\ListingApprovalService;
use App\Models\CarListing;
use App\Traits\ApiResponse;
use App\Traits\NotificationTrait;
use Illuminate\Http\Request;
use Exception;

class ListingApprovalController extends Controller
{
    use ApiResponse, NotificationTrait;

    protected ListingApprovalService $listingApprovalService;

    public function __construct(ListingApprovalService $listingApprovalService)
    {
        $this->listingApprovalService = $listingApprovalService;
    }

    public function approve(Request $request, int $listingId)
    {
        try {
            $result = $this->listingApprovalService->approveListing(
                $listingId,
                $request->user()?->id
            );

            // Get the listing to send notification
            $carListing = CarListing::find($listingId);
            if ($carListing && $carListing->odoo_id) {
                $this->sendListingStatusNotification($carListing->odoo_id, 'approved');
            }

            return $this->successResponse($result, 'Listing approved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to approve listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function reject(ListingApprovalRequest $request, int $listingId)
    {
        try {
            $validated = $request->validated();
            
            $result = $this->listingApprovalService->rejectListing(
                $listingId,
                $validated['reason'] ?? 'No reason provided',
                $request->user()?->id
            );

            // Get the listing to send notification
            $carListing = CarListing::find($listingId);
            if ($carListing && $carListing->odoo_id) {
                $this->sendListingStatusNotification($carListing->odoo_id, 'rejected', $validated['reason'] ?? 'No reason provided');
            }

            return $this->successResponse($result, 'Listing rejected successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to reject listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function process(ListingApprovalRequest $request, int $listingId)
    {
        try {
            $validated = $request->validated();
            $action = $validated['action'];
            $reason = $validated['reason'] ?? null;
            $operatorId = $request->user()?->id;

            $result = $this->listingApprovalService->processListing($listingId, $action, $reason, $operatorId);

            return $this->successResponse($result, "Listing {$action}d successfully");

        } catch (Exception $e) {
            return $this->errorResponse(
                "Failed to {$validated['action']} listing: " . $e->getMessage(),
                500
            );
        }
    }

    public function approveByOdooId(Request $request, string $odooId)
    {
        try {
            $result = $this->listingApprovalService->approveListingByOdooId(
                $odooId,
                $request->user()?->id
            );

            // Send notification to user about listing approval
            $this->sendListingStatusNotification($odooId, 'approved');

            return $this->successResponse($result, 'Listing approved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to approve listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function rejectByOdooId(Request $request, string $odooId)
    {
        try {
            $reason = $request->input('reason') ?? $request->input('rejection_reason') ?? 'No reason provided';
            
            $result = $this->listingApprovalService->rejectListingByOdooId(
                $odooId,
                $reason,
                $request->user()?->id
            );

            // Send notification to user about listing rejection
            $this->sendListingStatusNotification($odooId, 'rejected', $reason);

            return $this->successResponse($result, 'Listing rejected successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to reject listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function processByOdooId(Request $request, string $odooId)
    {
        try {
            $action = $request->input('action') ?? 'approve';
            $reason = $request->input('reason') ?? $request->input('rejection_reason') ?? null;
            $operatorId = $request->user()?->id;

            $result = $this->listingApprovalService->processListingByOdooId($odooId, $action, $reason, $operatorId);

            // Send notification to user about listing status change
            $this->sendListingStatusNotification($odooId, $action, $reason);

            return $this->successResponse($result, "Listing {$action}d successfully");

        } catch (Exception $e) {
            return $this->errorResponse(
                "Failed to {$action} listing: " . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send notification to user about listing status change
     */
    private function sendListingStatusNotification(string $odooId, string $status, string $reason = null)
    {
        try {
            // Find the car listing by Odoo ID
            $carListing = CarListing::where('odoo_id', $odooId)->first();
            if (!$carListing) {
                \Illuminate\Support\Facades\Log::warning('Car listing not found for notification', [
                    'odoo_id' => $odooId,
                    'status' => $status
                ]);
                return;
            }

            $user = $carListing->user;
            if (!$user) {
                \Illuminate\Support\Facades\Log::warning('No user found for listing notification', [
                    'car_listing_id' => $carListing->id,
                    'odoo_id' => $odooId
                ]);
                return;
            }

            // Get or create notification type based on status
            $notificationTypeName = $status === 'approved' ? 'listing_approved' : 'listing_rejected';
            $notificationType = \App\Models\NotificationType::firstOrCreate(
                ['name' => $notificationTypeName],
                [
                    'name' => $notificationTypeName,
                    'display_name' => $status === 'approved' ? 'Listing Approved' : 'Listing Rejected',
                    'description' => $status === 'approved' 
                        ? 'Your car listing has been approved and is now live' 
                        : 'Your car listing has been rejected',
                    'icon' => $status === 'approved' ? 'check_circle' : 'cancel',
                    'color' => $status === 'approved' ? '#10B981' : '#EF4444',
                    'is_active' => true,
                    'default_template' => [
                        'title' => $status === 'approved' 
                            ? 'Listing Approved!' 
                            : 'Listing Rejected',
                        'body' => $status === 'approved'
                            ? 'Your {{car_name}} listing is now live and visible to buyers.'
                            : 'Your {{car_name}} listing was rejected. Reason: {{reason}}'
                    ],
                    'channels' => ['push', 'email'],
                    'sort_order' => $status === 'approved' ? 2 : 3
                ]
            );

            // Get car name for notification
            $carName = $carListing->title;
            if ($carListing->brand && $carListing->carModel) {
                $carName = $carListing->brand->name . ' ' . $carListing->carModel->name . ' ' . $carListing->year;
            }

            // Prepare notification data
            $title = $status === 'approved' 
                ? 'Listing Approved!' 
                : 'Listing Rejected';
            
            $body = $status === 'approved'
                ? "Great news! Your {$carName} listing has been approved and is now live. Buyers can now see and contact you about this vehicle."
                : "Your {$carName} listing was not approved. Reason: " . ($reason ?? 'No reason provided') . ". Please review the requirements and submit a new listing.";

            // Send notification to user
            $this->sendNotificationToUser(
                $user->id,
                $title,
                $body,
                $notificationType->id,
                [
                    'car_listing_id' => $carListing->id,
                    'odoo_id' => $odooId,
                    'car_name' => $carName,
                    'price' => $carListing->price,
                    'currency' => $carListing->price_currency,
                    'status' => $status,
                    'reason' => $reason,
                    'approved_at' => $status === 'approved' ? now()->toISOString() : null,
                    'rejected_at' => $status === 'rejected' ? now()->toISOString() : null,
                ]
            );

            \Illuminate\Support\Facades\Log::info('Listing status notification sent', [
                'user_id' => $user->id,
                'car_listing_id' => $carListing->id,
                'odoo_id' => $odooId,
                'status' => $status,
                'car_name' => $carName
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send listing status notification', [
                'odoo_id' => $odooId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
}

