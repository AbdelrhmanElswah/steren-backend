<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Subscription\SubscribeRequest;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class SubscriptionController extends Controller
{
    use ApiResponse;

    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get all available subscription plans
     */
    public function getPlans(Request $request)
    {
        try {
            $plans = $this->subscriptionService->getAvailablePlans();

            return $this->successResponse([
                'plans' => $plans
            ], 'Subscription plans retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve subscription plans: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get user's current subscription
     */
    public function getCurrentSubscription(Request $request)
    {
        try {
            $user = $request->user();
            $subscription = $this->subscriptionService->getActiveSubscription($user);
            
            if (!$subscription) {
                return $this->successResponse([
                    'has_subscription' => false,
                    'subscription' => null
                ], 'No active subscription found');
            }

            return $this->successResponse([
                'has_subscription' => true,
                'subscription' => $subscription->load('subscriptionPlan'),
                'days_remaining' => $subscription->days_remaining,
                'formatted_expiry' => $subscription->formatted_expiry_date
            ], 'Current subscription retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve subscription: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get subscription limits and usage
     */
    public function getLimits(Request $request)
    {
        try {
            $user = $request->user();
            $limits = $this->subscriptionService->getSubscriptionLimits($user);

            return $this->successResponse($limits, 'Subscription limits retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve subscription limits: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(SubscribeRequest $request)
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $subscription = $this->subscriptionService->subscribe(
                $user,
                $validated['subscription_plan_id'],
                $validated['payment_details'] ?? null
            );

            return $this->createdResponse([
                'subscription' => $subscription->load('subscriptionPlan'),
                'message' => 'Subscription created successfully'
            ], 'Successfully subscribed to plan');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to subscribe: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request)
    {
        try {
            $user = $request->user();
            $result = $this->subscriptionService->cancelSubscription($user);

            if (!$result) {
                return $this->errorResponse('No active subscription found to cancel', 404);
            }

            return $this->successResponse([
                'cancelled' => true
            ], 'Subscription cancelled successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to cancel subscription: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Renew subscription
     */
    public function renew(Request $request)
    {
        try {
            $user = $request->user();
            $paymentDetails = $request->input('payment_details');

            $subscription = $this->subscriptionService->renewSubscription($user, $paymentDetails);

            if (!$subscription) {
                return $this->errorResponse('No active subscription found to renew', 404);
            }

            return $this->successResponse([
                'subscription' => $subscription->load('subscriptionPlan'),
                'message' => 'Subscription renewed successfully'
            ], 'Subscription renewed successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to renew subscription: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Request $request)
    {
        try {
            $user = $request->user();
            $validated = $request->validate([
                'subscription_plan_id' => 'required|integer|exists:subscription_plans,id',
                'payment_details' => 'nullable|array'
            ]);

            $subscription = $this->subscriptionService->changePlan(
                $user,
                $validated['subscription_plan_id'],
                $validated['payment_details'] ?? null
            );

            return $this->successResponse([
                'subscription' => $subscription->load('subscriptionPlan'),
                'message' => 'Subscription plan changed successfully'
            ], 'Plan changed successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to change plan: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get subscription history
     */
    public function getHistory(Request $request)
    {
        try {
            $user = $request->user();
            $subscriptions = $user->subscriptions()
                ->with('subscriptionPlan')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return $this->successResponse([
                'subscriptions' => $subscriptions
            ], 'Subscription history retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve subscription history: ' . $e->getMessage(),
                500
            );
        }
    }
}

