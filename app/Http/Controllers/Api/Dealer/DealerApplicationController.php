<?php

namespace App\Http\Controllers\Api\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\DealerApplicationRequest;
use App\Models\DealerApplication;
use App\Models\SubscriptionPlan;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DealerApplicationController extends Controller
{
    use ApiResponse;

    /**
     * Get dealer application status for the authenticated user
     */
    public function status(Request $request)
    {
        $user = $request->user();
        
        $latestApplication = $user->latestDealerApplication;
        $activeApplication = $user->activeDealerApplication;

        if (!$latestApplication) {
            return $this->successResponse([
                'has_application' => false,
                'status' => null,
                'message' => 'No dealer application found'
            ]);
        }

        $response = [
            'has_application' => true,
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
                    'formatted_price' => $latestApplication->subscriptionPlan->formatted_price,
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

        // Add specific messages based on status
        switch ($latestApplication->status) {
            case 'pending':
                $response['message'] = 'Your application is pending review';
                break;
            case 'under_review':
                $response['message'] = 'Your application is under review';
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
                    $response['message'] = 'Your application has been approved. Please complete payment to activate your dealer account';
                }
                break;
            case 'rejected':
                $response['message'] = 'Your application was not approved';
                break;
        }

        return $this->successResponse($response);
    }

    /**
     * Get available subscription plans
     */
    public function subscriptionPlans()
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'currency' => $plan->currency,
                    'billing_cycle' => $plan->billing_cycle,
                    'features' => $plan->features,
                    'max_listings' => $plan->max_listings,
                    'is_popular' => $plan->is_popular,
                    'sort_order' => $plan->sort_order,
                ];
            });

        return $this->successResponse([
            'subscription_plans' => $plans
        ]);
    }

    /**
     * Submit a new dealer application
     */
    public function store(DealerApplicationRequest $request)
    {
        $user = $request->user();

        // Check if user already has a pending or approved application
        $existingApplication = $user->dealerApplications()
            ->whereIn('status', ['pending', 'under_review', 'approved'])
            ->first();

        if ($existingApplication) {
            return $this->errorResponse(
                'You already have a dealer application in progress',
                422,
                ['existing_application_id' => $existingApplication->id]
            );
        }

        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            // Handle file upload for authorization letter
            if ($request->hasFile('authorization_letter')) {
                $file = $request->file('authorization_letter');
                $path = $file->store('dealer-applications/authorization-letters', 'public');
                $data['authorization_letter_path'] = $path;
            }

            // Remove file from validated data as it's not a database field
            unset($data['authorization_letter']);

            $application = $user->dealerApplications()->create($data);

            DB::commit();

            return $this->successResponse([
                'application' => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'message' => 'Dealer application submitted successfully'
                ]
            ], 'Dealer application submitted successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded file if transaction fails
            if (isset($data['authorization_letter_path'])) {
                Storage::disk('public')->delete($data['authorization_letter_path']);
            }

            return $this->errorResponse(
                'Failed to submit dealer application',
                500
            );
        }
    }

    /**
     * Get dealer application details
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $application = $user->dealerApplications()->findOrFail($id);

        return $this->successResponse([
            'application' => [
                'id' => $application->id,
                'seller_type' => $application->seller_type,
                'owner_manager_name' => $application->owner_manager_name,
                'phone_number' => $application->phone_number,
                'email' => $application->email,
                'dealership_company_name' => $application->dealership_company_name,
                'vehicles_in_stock' => $application->vehicles_in_stock,
                'years_in_business' => $application->years_in_business,
                'city_address' => $application->city_address,
                'automotive_brand_represented' => $application->automotive_brand_represented,
                'authorization_letter_url' => $application->authorization_letter_path 
                    ? Storage::disk('public')->url($application->authorization_letter_path) 
                    : null,
                'business_license_number' => $application->business_license_number,
                'status' => $application->status,
                'status_badge_color' => $application->status_badge_color,
                'subscription_plan' => $application->subscriptionPlan ? [
                    'id' => $application->subscriptionPlan->id,
                    'name' => $application->subscriptionPlan->name,
                    'price' => $application->subscriptionPlan->price,
                    'formatted_price' => $application->subscriptionPlan->formatted_price,
                ] : null,
                'payment_completed' => $application->payment_completed,
                'terms_accepted' => $application->terms_accepted,
                'information_confirmed' => $application->information_confirmed,
                'created_at' => $application->created_at,
                'reviewed_at' => $application->reviewed_at,
                'approved_at' => $application->approved_at,
                'expires_at' => $application->expires_at,
                'rejection_reason' => $application->rejection_reason,
                'reviewer' => $application->reviewer ? [
                    'id' => $application->reviewer->id,
                    'name' => $application->reviewer->full_name,
                ] : null,
            ]
        ]);
    }

    /**
     * Update dealer application (only if pending)
     */
    public function update(DealerApplicationRequest $request, $id)
    {
        $user = $request->user();
        
        $application = $user->dealerApplications()->findOrFail($id);

        if (!$application->isPending()) {
            return $this->errorResponse(
                'Cannot update application that is not pending',
                422
            );
        }

        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            // Handle file upload for authorization letter
            if ($request->hasFile('authorization_letter')) {
                // Delete old file if exists
                if ($application->authorization_letter_path) {
                    Storage::disk('public')->delete($application->authorization_letter_path);
                }
                
                $file = $request->file('authorization_letter');
                $path = $file->store('dealer-applications/authorization-letters', 'public');
                $data['authorization_letter_path'] = $path;
            }

            // Remove file from validated data as it's not a database field
            unset($data['authorization_letter']);

            $application->update($data);

            DB::commit();

            return $this->successResponse([
                'application' => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'message' => 'Dealer application updated successfully'
                ]
            ], 'Dealer application updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to update dealer application',
                500
            );
        }
    }

    /**
     * Cancel dealer application (only if pending)
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        $application = $user->dealerApplications()->findOrFail($id);

        if (!$application->isPending()) {
            return $this->errorResponse(
                'Cannot cancel application that is not pending',
                422
            );
        }

        try {
            // Delete authorization letter file if exists
            if ($application->authorization_letter_path) {
                Storage::disk('public')->delete($application->authorization_letter_path);
            }

            $application->delete();

            return $this->successResponse(
                null,
                'Dealer application cancelled successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to cancel dealer application',
                500
            );
        }
    }
}
