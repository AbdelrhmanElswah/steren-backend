<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarListingRequest;
use App\Http\Requests\Api\Odoo\UpdateCarListingRequest;
use App\Http\Resources\Api\Odoo\CarListingResource;
use App\Services\Odoo\CarListingService;
use App\Traits\ApiResponse;
use App\Traits\NotificationTrait;

class CarListingController extends Controller
{
    use ApiResponse, NotificationTrait;

    public function __construct(
        private CarListingService $CarListingService
    ) {}

    public function store(StoreCarListingRequest $request)
    {
        $CarListing = $this->CarListingService->create($request->validated());

        return $this->createdResponse([
            'car_listing' => new CarListingResource($CarListing),
            'odoo_sync' => [
                'status' => $CarListing->odoo_id ? 'success' : 'pending',
                'odoo_id' => $CarListing->odoo_id,
                'message' => $CarListing->odoo_id ? 'Successfully synced to Odoo' : 'Odoo sync pending or failed'
            ]
        ], 'CarListing created successfully and synced to Odoo');
    }

    public function updateByOdooId(UpdateCarListingRequest $request, string $odooId)
    {
        $CarListing = $this->CarListingService->updateByOdooId($odooId, $request->validated());

        // Send notification to user about listing update/re-enter
        $this->sendListingUpdatedNotification($CarListing);

        return $this->successResponse(
            new CarListingResource($CarListing),
            'CarListing updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->CarListingService->deleteByOdooId($odooId);

        return $this->noContentResponse('CarListing deleted successfully');
    }public function restoreByOdooId(string $odooId)
    {
        $CarListing = $this->CarListingService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarListingResource($CarListing),
            'CarListing restored successfully'
        );
    }

    /**
     * Send notification to user about listing update/re-enter
     */
    private function sendListingUpdatedNotification($carListing)
    {
        try {
            $user = $carListing->user;
            if (!$user) {
                \Illuminate\Support\Facades\Log::warning('No user found for listing update notification', [
                    'car_listing_id' => $carListing->id,
                    'odoo_id' => $carListing->odoo_id
                ]);
                return;
            }

            // Get or create listing updated notification type
            $notificationType = \App\Models\NotificationType::firstOrCreate(
                ['name' => 'listing_updated'],
                [
                    'name' => 'listing_updated',
                    'display_name' => 'Listing Updated',
                    'description' => 'Your car listing has been updated successfully',
                    'icon' => 'edit',
                    'color' => '#3B82F6',
                    'is_active' => true,
                    'default_template' => [
                        'title' => 'Listing Updated Successfully!',
                        'body' => 'Your {{car_name}} listing has been updated and is being reviewed again.'
                    ],
                    'channels' => ['push'],
                    'sort_order' => 18
                ]
            );

            // Get car name for notification
            $carName = $carListing->title;
            if ($carListing->brand && $carListing->carModel) {
                $carName = $carListing->brand->name . ' ' . $carListing->carModel->name . ' ' . $carListing->year;
            }

            // Prepare notification data
            $title = 'Listing Updated Successfully!';
            $body = "Your {$carName} listing has been updated successfully. The changes have been submitted for review and your listing will be processed again.";

            // Send notification to user
            $this->sendNotificationToUser(
                $user->id,
                $title,
                $body,
                $notificationType->id,
                [
                    'car_listing_id' => $carListing->id,
                    'odoo_id' => $carListing->odoo_id,
                    'car_name' => $carName,
                    'price' => $carListing->price,
                    'currency' => $carListing->price_currency,
                    'status' => $carListing->status,
                    'updated_at' => now()->toISOString()
                ]
            );

            \Illuminate\Support\Facades\Log::info('Listing updated notification sent', [
                'user_id' => $user->id,
                'car_listing_id' => $carListing->id,
                'odoo_id' => $carListing->odoo_id,
                'car_name' => $carName
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send listing updated notification', [
                'car_listing_id' => $carListing->id,
                'odoo_id' => $carListing->odoo_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}