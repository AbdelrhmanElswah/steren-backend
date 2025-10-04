<?php

namespace App\Http\Controllers\Api\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\SubscriptionPlanRequest;
use App\Models\DealerApplication;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealerSubscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Get current subscription details for the authenticated user
     */
    public function current(Request $request)
    {
        $user = $request->user();
        
        $dealerApplication = $user->activeDealerApplication;
        
        if (!$dealerApplication) {
            return $this->errorResponse(
                'No active dealer subscription found',
                404
            );
        }

        $subscriptionPlan = $dealerApplication->subscriptionPlan;
        
        if (!$subscriptionPlan) {
            return $this->errorResponse(
                'Subscription plan not found',
                404
            );
        }

        return $this->successResponse([
            'current_plan' => [
                'id' => $subscriptionPlan->id,
                'name' => $subscriptionPlan->name,
                'description' => $subscriptionPlan->description,
                'price' => $subscriptionPlan->price,
                'formatted_price' => $subscriptionPlan->formatted_price,
                'currency' => $subscriptionPlan->currency,
                'billing_cycle' => $subscriptionPlan->billing_cycle,
                'features' => $subscriptionPlan->features,
                'max_listings' => $subscriptionPlan->max_listings,
                'is_popular' => $subscriptionPlan->is_popular,
            ],
            'subscription' => [
                'status' => $dealerApplication->payment_completed ? 'active' : 'inactive',
                'payment_completed' => $dealerApplication->payment_completed,
                'started_at' => $dealerApplication->approved_at,
                'expires_at' => $dealerApplication->expires_at,
                'days_until_expiration' => $dealerApplication->days_until_expiration,
                'is_active' => $dealerApplication->isActive(),
                'is_expiring_soon' => $dealerApplication->isExpiringSoon(),
                'is_expired' => $dealerApplication->isExpired(),
            ]
        ]);
    }

    /**
     * Get all available subscription plans
     */
    public function plans()
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
     * Update subscription plan for approved dealer application
     */
    public function updatePlan(SubscriptionPlanRequest $request)
    {
        $user = $request->user();
        
        $dealerApplication = $user->activeDealerApplication;
        
        if (!$dealerApplication) {
            return $this->errorResponse(
                'No active dealer application found',
                404
            );
        }

        if (!$dealerApplication->isApproved()) {
            return $this->errorResponse(
                'Cannot update subscription plan for non-approved application',
                422
            );
        }

        $newPlan = SubscriptionPlan::findOrFail($request->subscription_plan_id);
        
        if ($dealerApplication->subscription_plan_id === $newPlan->id) {
            return $this->errorResponse(
                'You are already subscribed to this plan',
                422
            );
        }

        try {
            DB::beginTransaction();

            // Update the subscription plan
            $dealerApplication->update([
                'subscription_plan_id' => $newPlan->id,
            ]);

            // If payment was already completed, update the expiration date
            if ($dealerApplication->payment_completed) {
                $billingCycle = $newPlan->billing_cycle;
                $expiresAt = $billingCycle === 'yearly' 
                    ? now()->addYear() 
                    : now()->addMonth();
                
                $dealerApplication->update(['expires_at' => $expiresAt]);
            }

            DB::commit();

            return $this->successResponse([
                'updated_plan' => [
                    'id' => $newPlan->id,
                    'name' => $newPlan->name,
                    'formatted_price' => $newPlan->formatted_price,
                ],
                'message' => 'Subscription plan updated successfully'
            ], 'Subscription plan updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to update subscription plan',
                500
            );
        }
    }

    /**
     * Complete payment for dealer application
     */
    public function completePayment(SubscriptionPlanRequest $request)
    {
        $user = $request->user();
        
        $dealerApplication = $user->latestDealerApplication;
        
        if (!$dealerApplication) {
            return $this->errorResponse(
                'No dealer application found',
                404
            );
        }

        if (!$dealerApplication->isApproved()) {
            return $this->errorResponse(
                'Cannot complete payment for non-approved application',
                422
            );
        }

        if ($dealerApplication->payment_completed) {
            return $this->errorResponse(
                'Payment already completed for this application',
                422
            );
        }

        try {
            DB::beginTransaction();

            // Complete payment
            $dealerApplication->completePayment();

            // Create user subscription record
            $userSubscription = UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $dealerApplication->subscription_plan_id,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => $dealerApplication->expires_at,
                'payment_details' => [
                    'payment_method' => $request->payment_method,
                    'payment_data' => $request->payment_data,
                    'dealer_application_id' => $dealerApplication->id,
                ],
            ]);

            DB::commit();

            return $this->successResponse([
                'subscription' => [
                    'id' => $userSubscription->id,
                    'status' => $userSubscription->status,
                    'started_at' => $userSubscription->started_at,
                    'expires_at' => $userSubscription->expires_at,
                ],
                'dealer_application' => [
                    'id' => $dealerApplication->id,
                    'payment_completed' => $dealerApplication->payment_completed,
                    'is_active' => $dealerApplication->isActive(),
                ],
                'message' => 'Payment completed successfully. You are now a verified dealer!'
            ], 'Payment completed successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to complete payment',
                500
            );
        }
    }

    /**
     * Renew subscription for expired dealer application
     */
    public function renew(SubscriptionPlanRequest $request)
    {
        $user = $request->user();
        
        $dealerApplication = $user->latestDealerApplication;
        
        if (!$dealerApplication) {
            return $this->errorResponse(
                'No dealer application found',
                404
            );
        }

        if (!$dealerApplication->isExpired()) {
            return $this->errorResponse(
                'Cannot renew non-expired subscription',
                422
            );
        }

        try {
            DB::beginTransaction();

            // Update subscription plan if different
            if ($request->subscription_plan_id !== $dealerApplication->subscription_plan_id) {
                $dealerApplication->update([
                    'subscription_plan_id' => $request->subscription_plan_id,
                ]);
            }

            // Reset payment status and complete payment
            $dealerApplication->update(['payment_completed' => false]);
            $dealerApplication->completePayment();

            // Create new user subscription record
            $userSubscription = UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $dealerApplication->subscription_plan_id,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => $dealerApplication->expires_at,
                'payment_details' => [
                    'payment_method' => $request->payment_method,
                    'payment_data' => $request->payment_data,
                    'dealer_application_id' => $dealerApplication->id,
                    'renewal' => true,
                ],
            ]);

            DB::commit();

            return $this->successResponse([
                'subscription' => [
                    'id' => $userSubscription->id,
                    'status' => $userSubscription->status,
                    'started_at' => $userSubscription->started_at,
                    'expires_at' => $userSubscription->expires_at,
                ],
                'dealer_application' => [
                    'id' => $dealerApplication->id,
                    'payment_completed' => $dealerApplication->payment_completed,
                    'is_active' => $dealerApplication->isActive(),
                    'expires_at' => $dealerApplication->expires_at,
                ],
                'message' => 'Subscription renewed successfully. Your dealer account is active again!'
            ], 'Subscription renewed successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to renew subscription',
                500
            );
        }
    }

    /**
     * Get billing history for the user
     */
    public function billingHistory(Request $request)
    {
        $user = $request->user();
        
        $subscriptions = UserSubscription::where('user_id', $user->id)
            ->with('subscriptionPlan')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->subscriptionPlan->name,
                    'amount' => $subscription->subscriptionPlan->price,
                    'currency' => $subscription->subscriptionPlan->currency,
                    'status' => $subscription->status,
                    'started_at' => $subscription->started_at,
                    'expires_at' => $subscription->expires_at,
                    'payment_details' => $subscription->payment_details,
                ];
            });

        return $this->successResponse([
            'billing_history' => $subscriptions
        ]);
    }
}
