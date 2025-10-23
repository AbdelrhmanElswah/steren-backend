<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\UserAnalytics;
use App\Models\PaymentMethod;
use App\Models\WalletTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    use ApiResponse;

    /**
     * Get user account information
     */
    public function getAccountInfo(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $data = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'email' => $user->email,
                    'phone' => $user->formatted_phone,
                    'profile_picture' => $user->profile_picture,
                    'birthday' => $user->formatted_birthday,
                    'age' => $user->age,
                    'language' => $user->language,
                    'theme' => $user->theme,
                    'is_verified' => $user->is_verified,
                    'notifications_enabled' => $user->notifications_enabled,
                    'total_listings' => $user->total_listings,
                    'total_views' => $user->total_views,
                    'total_favorites' => $user->total_favorites,
                ],
                'wallet' => $user->wallet ? [
                    'balance' => $user->wallet->balance,
                    'formatted_balance' => $user->wallet->formatted_balance,
                    'currency' => $user->wallet->currency,
                ] : null,
                'subscription' => $user->current_plan ? [
                    'id' => $user->current_plan->id,
                    'name' => $user->current_plan->name,
                    'price' => $user->current_plan->formatted_price,
                    'billing_cycle' => $user->current_plan->billing_cycle,
                    'expires_at' => $user->activeSubscription->formatted_expiry_date,
                    'days_remaining' => $user->activeSubscription->days_remaining,
                ] : null,
                'dealer_status' => [
                    'is_dealer' => $user->isDealer(),
                    'status' => $user->dealer_status,
                    'has_pending_application' => $user->hasPendingDealerApplication(),
                    'has_approved_application' => $user->hasApprovedDealerApplication(),
                    'is_expiring_soon' => $user->isDealerExpiringSoon(),
                    'is_expired' => $user->isDealerExpired(),
                ],
            ];

            return $this->successResponse($data, 'Account information retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve account information: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $days = $request->get('days', 30);
            
            $analytics = UserAnalytics::where('user_id', $user->id)
                ->lastDays($days)
                ->get()
                ->groupBy('metric_type');

            $summary = UserAnalytics::getAnalyticsSummary($user->id);

            $data = [
                'summary' => $summary,
                'daily_data' => $analytics,
                'period' => $days . ' days',
            ];

            return $this->successResponse($data, 'Analytics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's ads (car listings)
     */
    public function getMyAds(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $listings = $user->carListings()
                ->with([
                    'brand:id,name,odoo_id',
                    'carModel:id,name,car_brand_id', 
                    'bodyType:id,name',
                    'location:id,name',
                    'media:id,modelable_id,path_file_name,file_type,order',
                    'carCondition:id,name',
                    'fuelType:id,name',
                    'transmission:id,name',
                    'exteriorColor:id,name',
                    'driveType:id,name',
                    'cylinder:id,name',
                    'carTrim:id,name,car_model_id',
                    'features:id,name,category,description,icon',
                    'specifications:id,name,value,unit,specification_type,description,is_highlighted',
                    'badges:id,name,type,color,icon'
                ])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // Transform listings to include detailed specifications and features
            $transformedListings = $listings->items();
            foreach ($transformedListings as $listing) {
                // Add detailed specifications
                $listing->detailed_specifications = [
                    'engine_size' => $listing->engine_size,
                    'engine_type' => $listing->engine_type,
                    'horsepower' => $listing->horsepower,
                    'torque' => $listing->torque,
                    'transmission' => $listing->transmission?->name,
                    'cylinders' => $listing->cylinder?->name,
                    'drive_type' => $listing->driveType?->name,
                    'acceleration_0_100' => $listing->acceleration_0_100,
                    'top_speed' => $listing->top_speed,
                    'seating_capacity' => $listing->seating_capacity,
                    'mileage_efficiency' => $listing->formatted_mileage_efficiency,
                    'dimensions' => $listing->formatted_dimensions,
                ];

                // Group features by category
                $featuresByCategory = collect();
                if ($listing->relationLoaded('features') && $listing->features instanceof \Illuminate\Database\Eloquent\Collection && $listing->features->isNotEmpty()) {
                    $featuresByCategory = $listing->features->groupBy('category')->map(function ($features) {
                        return $features->map(function ($feature) {
                            return [
                                'id' => $feature->id,
                                'name' => $feature->name,
                                'description' => $feature->description,
                                'icon' => $feature->icon,
                                'is_included' => $feature->pivot->is_included ?? false,
                                'notes' => $feature->pivot->notes ?? null,
                            ];
                        });
                    });
                }
                $listing->features_by_category = $featuresByCategory;

                // Group specifications by type
                $specificationsByType = collect();
                if ($listing->relationLoaded('specifications') && $listing->specifications instanceof \Illuminate\Database\Eloquent\Collection && $listing->specifications->isNotEmpty()) {
                    $specificationsByType = $listing->specifications->groupBy('specification_type')->map(function ($specs) {
                        return $specs->map(function ($spec) {
                            return [
                                'id' => $spec->id,
                                'name' => $spec->name,
                                'value' => $spec->value,
                                'unit' => $spec->unit,
                                'formatted_value' => $spec->formatted_value,
                                'description' => $spec->description,
                                'is_highlighted' => $spec->is_highlighted,
                            ];
                        });
                    });
                }
                $listing->specifications_by_type = $specificationsByType;

                // Add status and badge information
                $listing->status_badge = $listing->status_badge;
                $listing->active_badges = $listing->active_badges;
            }

            $data = [
                'listings' => $transformedListings,
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
            ];

            return $this->successResponse($data, 'My ads retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve my ads: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get wallet information
     */
    public function getWallet(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $wallet = $user->wallet;
            
            if (!$wallet) {
                // Create wallet if it doesn't exist
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'balance' => 0.00,
                    'currency' => 'IQD',
                ]);
            }

            $data = [
                'balance' => $wallet->balance,
                'formatted_balance' => $wallet->formatted_balance,
                'currency' => $wallet->currency,
                'is_active' => $wallet->is_active,
                'last_transaction_at' => $wallet->last_transaction_at,
                'payment_methods' => $wallet->payment_methods,
            ];

            return $this->successResponse($data, 'Wallet information retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve wallet information: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get wallet transaction history
     */
    public function getWalletTransactions(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $wallet = $user->wallet;
            
            if (!$wallet) {
                return $this->successResponse(['transactions' => [], 'total' => 0], 'No wallet found');
            }

            $transactions = $wallet->transactions()
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $data = [
                'transactions' => $transactions->items(),
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ];

            return $this->successResponse($data, 'Wallet transactions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve wallet transactions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $paymentMethods = PaymentMethod::active()->ordered()->get();
            
            $data = $paymentMethods->map(function ($method) {
                return [
                    'id' => $method->id,
                    'name' => $method->name,
                    'display_name' => $method->display_name,
                    'type' => $method->type,
                    'type_label' => $method->type_label,
                    'gateway' => $method->gateway,
                    'description' => $method->description,
                    'logo_url' => $method->logo_url,
                    'supported_currencies' => $method->supported_currencies,
                    'min_amount' => $method->min_amount,
                    'max_amount' => $method->max_amount,
                    'formatted_min_amount' => $method->formatted_min_amount,
                    'formatted_max_amount' => $method->formatted_max_amount,
                ];
            });

            return $this->successResponse($data, 'Payment methods retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payment methods: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get subscription plans
     */
    public function getSubscriptionPlans(): JsonResponse
    {
        try {
            $plans = SubscriptionPlan::active()->ordered()->get();
            
            $data = $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'price_per_month' => $plan->formatted_price_per_month,
                    'currency' => $plan->currency,
                    'billing_cycle' => $plan->billing_cycle,
                    'features' => $plan->features,
                    'max_listings' => $plan->max_listings,
                    'is_popular' => $plan->is_popular,
                ];
            });

            return $this->successResponse($data, 'Subscription plans retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve subscription plans: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's subscription
     */
    public function getMySubscription(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $subscription = $user->activeSubscription;
            
            if (!$subscription) {
                return $this->successResponse(null, 'No active subscription found');
            }

            $data = [
                'id' => $subscription->id,
                'plan' => [
                    'id' => $subscription->subscriptionPlan->id,
                    'name' => $subscription->subscriptionPlan->name,
                    'description' => $subscription->subscriptionPlan->description,
                    'price' => $subscription->subscriptionPlan->formatted_price,
                    'billing_cycle' => $subscription->subscriptionPlan->billing_cycle,
                    'features' => $subscription->subscriptionPlan->features,
                ],
                'status' => $subscription->status,
                'started_at' => $subscription->started_at,
                'expires_at' => $subscription->expires_at,
                'formatted_expiry_date' => $subscription->formatted_expiry_date,
                'days_remaining' => $subscription->days_remaining,
                'is_active' => $subscription->isActive(),
            ];

            return $this->successResponse($data, 'Subscription information retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve subscription information: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get language options
     */
    public function getLanguageOptions(): JsonResponse
    {
        try {
            $languages = [
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'ku', 'label' => 'كوردي'],
                ['value' => 'ar', 'label' => 'العربية'],
            ];

            return $this->successResponse($languages, 'Language options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve language options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get theme options
     */
    public function getThemeOptions(): JsonResponse
    {
        try {
            $themes = [
                ['value' => 'light', 'label' => 'Light'],
                ['value' => 'dark', 'label' => 'Dark'],
                ['value' => 'system', 'label' => 'System Default'],
            ];

            return $this->successResponse($themes, 'Theme options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve theme options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get social authentication options
     */
    public function getSocialAuthOptions(): JsonResponse
    {
        try {
            $socialOptions = [
                ['provider' => 'apple', 'name' => 'Sign up With Apple', 'icon' => 'apple'],
                ['provider' => 'google', 'name' => 'Sign up With Google', 'icon' => 'google'],
                ['provider' => 'facebook', 'name' => 'Sign up With Facebook', 'icon' => 'facebook'],
            ];

            return $this->successResponse($socialOptions, 'Social authentication options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve social authentication options: ' . $e->getMessage(), 500);
        }
    }
}
