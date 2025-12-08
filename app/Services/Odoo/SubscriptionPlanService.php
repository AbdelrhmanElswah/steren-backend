<?php

namespace App\Services\Odoo;

use App\Models\SubscriptionPlan;

class SubscriptionPlanService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new SubscriptionPlan());
    }

    public function mapOdooDataToModel(array $odooData): array
    {
        // Normalize billing_cycle: Odoo sends 'month'/'year', we store 'monthly'/'yearly'
        $billingCycle = $odooData['billing_cycle'] ?? 'monthly';
        if ($billingCycle === 'month') {
            $billingCycle = 'monthly';
        } elseif ($billingCycle === 'year') {
            $billingCycle = 'yearly';
        }

        return [
            'odoo_id' => (string) ($odooData['plan_id'] ?? $odooData['id'] ?? ''),
            'name' => $odooData['name'],
            'name_ar' => $odooData['name_ar'] ?? null,
            'name_kr' => $odooData['name_kr'] ?? null,
            'description' => $odooData['description'] ?? null,
            'description_ar' => $odooData['description_ar'] ?? null,
            'description_kr' => $odooData['description_kr'] ?? null,
            'price' => $odooData['price'],
            'currency' => $odooData['currency'] ?? 'USD',
            'currency_symbol' => $odooData['currency_symbol'] ?? null,
            'billing_cycle' => $billingCycle,
            'features' => $odooData['features'] ?? [],
            'max_listings' => $odooData['max_listings'] ?? null,
            'max_photos' => $odooData['max_photos'] ?? null,
            'subscriber_count' => $odooData['subscriber_count'] ?? 0,
            'is_active' => $odooData['is_active'] ?? $odooData['active'] ?? true,
            'is_popular' => $odooData['is_popular'] ?? false,
            'is_featured' => $odooData['is_featured'] ?? false,
            'is_whatsapp_phone' => $odooData['is_whatsapp_phone'] ?? false,
            'is_badge' => $odooData['is_badge'] ?? false,
            'is_search' => $odooData['is_search'] ?? false,
            'is_similar_cars' => $odooData['is_similar_cars'] ?? false,
            'is_dedicated_account_rep' => $odooData['is_dedicated_account_rep'] ?? false,
            'is_analytics' => $odooData['is_analytics'] ?? false,
            'is_top_picks' => $odooData['is_top_picks'] ?? false,
            'is_priority_support' => $odooData['is_priority_support'] ?? false,
            'is_you_may_also_like' => $odooData['is_you_may_also_like'] ?? false,
            'sort_order' => $odooData['sort_order'] ?? 0,
        ];
    }

    /**
     * Map Odoo update data to model - only includes fields that are present in the update
     * This allows partial updates without overwriting existing data
     */
    public function mapOdooUpdateDataToModel(array $odooData): array
    {
        $mappedData = [];

        // Handle odoo_id if present (from plan_id or id)
        if (isset($odooData['plan_id'])) {
            $mappedData['odoo_id'] = (string) $odooData['plan_id'];
        } elseif (isset($odooData['id'])) {
            $mappedData['odoo_id'] = (string) $odooData['id'];
        }

        // Map only fields that are present in the update request
        if (isset($odooData['name'])) {
            $mappedData['name'] = $odooData['name'];
        }

        if (isset($odooData['name_ar'])) {
            $mappedData['name_ar'] = $odooData['name_ar'];
        }

        if (isset($odooData['name_kr'])) {
            $mappedData['name_kr'] = $odooData['name_kr'];
        }

        if (isset($odooData['description'])) {
            $mappedData['description'] = $odooData['description'];
        }

        if (isset($odooData['description_ar'])) {
            $mappedData['description_ar'] = $odooData['description_ar'];
        }

        if (isset($odooData['description_kr'])) {
            $mappedData['description_kr'] = $odooData['description_kr'];
        }

        if (isset($odooData['price'])) {
            $mappedData['price'] = $odooData['price'];
        }

        if (isset($odooData['currency'])) {
            $mappedData['currency'] = $odooData['currency'];
        }

        if (isset($odooData['currency_symbol'])) {
            $mappedData['currency_symbol'] = $odooData['currency_symbol'];
        }

        if (isset($odooData['billing_cycle'])) {
            // Normalize billing_cycle: Odoo sends 'month'/'year', we store 'monthly'/'yearly'
            $billingCycle = $odooData['billing_cycle'];
            if ($billingCycle === 'month') {
                $billingCycle = 'monthly';
            } elseif ($billingCycle === 'year') {
                $billingCycle = 'yearly';
            }
            $mappedData['billing_cycle'] = $billingCycle;
        }

        // Handle features: Use from Odoo if provided, otherwise compute from boolean flags if any feature flags changed
        if (isset($odooData['features'])) {
            $mappedData['features'] = $odooData['features'];
        } else {
            // Check if any feature flags are being updated
            $featureFlags = [
                'is_whatsapp_phone', 'is_badge', 'is_search', 'is_similar_cars',
                'is_dedicated_account_rep', 'is_analytics', 'is_top_picks',
                'is_priority_support', 'is_you_may_also_like',
                'max_listings', 'max_photos' // These also affect features
            ];
            
            $anyFeatureFlagChanged = false;
            foreach ($featureFlags as $flag) {
                if (isset($odooData[$flag])) {
                    $anyFeatureFlagChanged = true;
                    break;
                }
            }
            
            // If feature flags changed but features not provided, we'll compute it after update
            // Store a flag to indicate we need to recompute features
            if ($anyFeatureFlagChanged) {
                $mappedData['_recompute_features'] = true;
            }
        }

        if (isset($odooData['max_listings'])) {
            $mappedData['max_listings'] = $odooData['max_listings'];
        }

        if (isset($odooData['max_photos'])) {
            $mappedData['max_photos'] = $odooData['max_photos'];
        }

        if (isset($odooData['subscriber_count'])) {
            $mappedData['subscriber_count'] = $odooData['subscriber_count'];
        }

        // Handle is_active (Odoo might send 'active' or 'is_active')
        if (isset($odooData['is_active'])) {
            $mappedData['is_active'] = $odooData['is_active'];
        } elseif (isset($odooData['active'])) {
            $mappedData['is_active'] = $odooData['active'];
        }

        // Map all feature flags
        if (isset($odooData['is_popular'])) {
            $mappedData['is_popular'] = $odooData['is_popular'];
        }

        if (isset($odooData['is_featured'])) {
            $mappedData['is_featured'] = $odooData['is_featured'];
        }

        if (isset($odooData['is_whatsapp_phone'])) {
            $mappedData['is_whatsapp_phone'] = $odooData['is_whatsapp_phone'];
        }

        if (isset($odooData['is_badge'])) {
            $mappedData['is_badge'] = $odooData['is_badge'];
        }

        if (isset($odooData['is_search'])) {
            $mappedData['is_search'] = $odooData['is_search'];
        }

        if (isset($odooData['is_similar_cars'])) {
            $mappedData['is_similar_cars'] = $odooData['is_similar_cars'];
        }

        if (isset($odooData['is_dedicated_account_rep'])) {
            $mappedData['is_dedicated_account_rep'] = $odooData['is_dedicated_account_rep'];
        }

        if (isset($odooData['is_analytics'])) {
            $mappedData['is_analytics'] = $odooData['is_analytics'];
        }

        if (isset($odooData['is_top_picks'])) {
            $mappedData['is_top_picks'] = $odooData['is_top_picks'];
        }

        if (isset($odooData['is_priority_support'])) {
            $mappedData['is_priority_support'] = $odooData['is_priority_support'];
        }

        if (isset($odooData['is_you_may_also_like'])) {
            $mappedData['is_you_may_also_like'] = $odooData['is_you_may_also_like'];
        }

        if (isset($odooData['sort_order'])) {
            $mappedData['sort_order'] = $odooData['sort_order'];
        }

        return $mappedData;
    }

    public function createFromOdoo(array $odooData): SubscriptionPlan
    {
        $mappedData = $this->mapOdooDataToModel($odooData);
        
        // If features not provided, compute from boolean flags
        if (empty($mappedData['features'])) {
            $flags = [
                'name' => $mappedData['name'],
                'is_whatsapp_phone' => $mappedData['is_whatsapp_phone'] ?? false,
                'is_badge' => $mappedData['is_badge'] ?? false,
                'is_search' => $mappedData['is_search'] ?? false,
                'is_similar_cars' => $mappedData['is_similar_cars'] ?? false,
                'is_analytics' => $mappedData['is_analytics'] ?? false,
                'is_top_picks' => $mappedData['is_top_picks'] ?? false,
                'is_you_may_also_like' => $mappedData['is_you_may_also_like'] ?? false,
                'is_dedicated_account_rep' => $mappedData['is_dedicated_account_rep'] ?? false,
                'is_priority_support' => $mappedData['is_priority_support'] ?? false,
            ];
            
            $mappedData['features'] = $this->computeFeaturesFromFlags(
                $flags,
                $mappedData['max_listings'] ?? null,
                $mappedData['max_photos'] ?? null
            );
        }
        
        return $this->create($mappedData);
    }

    /**
     * Compute features array from boolean flags (matching Odoo's _prepare_api_data logic)
     */
    private function computeFeaturesFromFlags(array $flags, ?int $maxListings = null, ?int $maxPhotos = null): array
    {
        $featureList = [];

        // Add listing and photo limits to features (always first)
        if ($maxListings !== null) {
            $featureList[] = "Up to {$maxListings} car listings per month";
        }
        if ($maxPhotos !== null) {
            $featureList[] = "Up to {$maxPhotos} photos per listing";
        }

        // Build feature list from boolean flags
        if (!empty($flags['is_whatsapp_phone'])) {
            $featureList[] = "WhatsApp Contact";
        }
        if (!empty($flags['is_badge'])) {
            $planName = $flags['name'] ?? 'Plan';
            $featureList[] = "Plan Badge ({$planName})";
        }
        if (!empty($flags['is_search'])) {
            $featureList[] = "Advanced Search";
        }
        if (!empty($flags['is_similar_cars'])) {
            $featureList[] = "Similar Cars Display";
        }
        if (!empty($flags['is_analytics'])) {
            $featureList[] = "Analytics Dashboard";
        }
        if (!empty($flags['is_top_picks'])) {
            $featureList[] = "Featured in Top Picks";
        }
        if (!empty($flags['is_you_may_also_like'])) {
            $featureList[] = "You May Also Like Recommendations";
        }
        if (!empty($flags['is_dedicated_account_rep'])) {
            $featureList[] = "Dedicated Account Representative";
        }
        if (!empty($flags['is_priority_support'])) {
            $featureList[] = "Priority Support";
        }

        return $featureList;
    }

    public function updateFromOdoo(string $odooId, array $odooData): SubscriptionPlan
    {
        // Use update-specific mapping that only includes fields present in the update
        $mappedData = $this->mapOdooUpdateDataToModel($odooData);
        
        // Check if we need to recompute features
        $recomputeFeatures = isset($mappedData['_recompute_features']);
        unset($mappedData['_recompute_features']);
        
        // Update the plan
        $plan = $this->updateByOdooId($odooId, $mappedData);
        
        // If features need to be recomputed, do it now (after we have the updated plan data)
        if ($recomputeFeatures) {
            // Get the updated plan with all current values
            $plan->refresh();
            
            // Build flags array from current plan data and any updates
            $flags = [
                'name' => $plan->name,
                'is_whatsapp_phone' => $plan->is_whatsapp_phone,
                'is_badge' => $plan->is_badge,
                'is_search' => $plan->is_search,
                'is_similar_cars' => $plan->is_similar_cars,
                'is_analytics' => $plan->is_analytics,
                'is_top_picks' => $plan->is_top_picks,
                'is_you_may_also_like' => $plan->is_you_may_also_like,
                'is_dedicated_account_rep' => $plan->is_dedicated_account_rep,
                'is_priority_support' => $plan->is_priority_support,
            ];
            
            // Compute features from flags
            $features = $this->computeFeaturesFromFlags($flags, $plan->max_listings, $plan->max_photos);
            
            // Update features
            $plan->update(['features' => $features]);
            $plan->refresh();
        }
        
        return $plan;
    }

    public function syncFromOdoo(array $odooPlans): array
    {
        $synced = [];
        $errors = [];

        foreach ($odooPlans as $oodooPlan) {
            try {
                $odooId = (string) $oodooPlan['plan_id'];
                
                $existingPlan = SubscriptionPlan::where('odoo_id', $odooId)->first();
                
                if ($existingPlan) {
                    $plan = $this->updateFromOdoo($odooId, $oodooPlan);
                    $synced[] = [
                        'action' => 'updated',
                        'plan' => $plan,
                    ];
                } else {
                    $plan = $this->createFromOdoo($oodooPlan);
                    $synced[] = [
                        'action' => 'created',
                        'plan' => $plan,
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'odoo_id' => $odooId ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'total_synced' => count($synced),
            'total_errors' => count($errors),
        ];
    }
}

