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
        return [
            'odoo_id' => (string) $odooData['plan_id'],
            'name' => $odooData['name'],
            'description' => $odooData['description'] ?? null,
            'price' => $odooData['price'],
            'currency' => $odooData['currency'] ?? 'USD',
            'currency_symbol' => $odooData['currency_symbol'] ?? null,
            'billing_cycle' => $odooData['billing_cycle'] ?? 'monthly',
            'features' => $odooData['features'] ?? [],
            'max_listings' => $odooData['max_listings'] ?? null,
            'max_photos' => $odooData['max_photos'] ?? null,
            'subscriber_count' => $odooData['subscriber_count'] ?? 0,
            'is_active' => true,
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
            'sort_order' => 0,
        ];
    }

    public function createFromOdoo(array $odooData): SubscriptionPlan
    {
        $mappedData = $this->mapOdooDataToModel($odooData);
        return $this->create($mappedData);
    }

    public function updateFromOdoo(string $odooId, array $odooData): SubscriptionPlan
    {
        $mappedData = $this->mapOdooDataToModel($odooData);
        return $this->updateByOdooId($odooId, $mappedData);
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

