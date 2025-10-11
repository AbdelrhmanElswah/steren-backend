<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListingPromotion;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ListingPromotionController extends Controller
{
    use ApiResponse;

    /**
     * Get all active listing promotions
     */
    public function index()
    {
        $promotions = ListingPromotion::getActivePromotions();

        return $this->successResponse([
            'promotions' => $promotions->map(function ($promotion) {
                return [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'type' => $promotion->type,
                    'description' => $promotion->description,
                    'price' => $promotion->price,
                    'formatted_price' => $promotion->formatted_price,
                    'currency' => $promotion->currency,
                    'features' => $promotion->features,
                    'visibility_benefits' => $promotion->visibility_benefits,
                    'visibility_summary' => $promotion->visibility_summary,
                    'duration_days' => $promotion->getDurationInDays(),
                    'is_sponsored' => $promotion->isSponsored(),
                    'is_free' => $promotion->isFree(),
                    'priority_order' => $promotion->priority_order,
                ];
            })
        ]);
    }

    /**
     * Get specific promotion details
     */
    public function show($id)
    {
        $promotion = ListingPromotion::find($id);

        if (!$promotion || !$promotion->is_active) {
            return $this->errorResponse('Promotion not found', 404);
        }

        return $this->successResponse([
            'promotion' => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'description' => $promotion->description,
                'price' => $promotion->price,
                'formatted_price' => $promotion->formatted_price,
                'currency' => $promotion->currency,
                'features' => $promotion->features,
                'visibility_benefits' => $promotion->visibility_benefits,
                'visibility_summary' => $promotion->visibility_summary,
                'duration_days' => $promotion->getDurationInDays(),
                'is_sponsored' => $promotion->isSponsored(),
                'is_free' => $promotion->isFree(),
                'priority_order' => $promotion->priority_order,
                'features_html' => $promotion->features_as_html,
            ]
        ]);
    }

    /**
     * Get promotion pricing information
     */
    public function pricing()
    {
        $sponsoredPromotion = ListingPromotion::getSponsored();
        $freePromotion = ListingPromotion::getFree();

        return $this->successResponse([
            'pricing' => [
                'sponsored' => $sponsoredPromotion ? [
                    'id' => $sponsoredPromotion->id,
                    'name' => $sponsoredPromotion->name,
                    'price' => $sponsoredPromotion->price,
                    'formatted_price' => $sponsoredPromotion->formatted_price,
                    'currency' => $sponsoredPromotion->currency,
                    'duration_days' => $sponsoredPromotion->getDurationInDays(),
                    'features' => $sponsoredPromotion->features,
                    'visibility_benefits' => $sponsoredPromotion->visibility_benefits,
                ] : null,
                'free' => $freePromotion ? [
                    'id' => $freePromotion->id,
                    'name' => $freePromotion->name,
                    'price' => $freePromotion->price,
                    'formatted_price' => $freePromotion->formatted_price,
                    'currency' => $freePromotion->currency,
                    'duration_days' => $freePromotion->getDurationInDays(),
                    'features' => $freePromotion->features,
                    'visibility_benefits' => $freePromotion->visibility_benefits,
                ] : null,
            ]
        ]);
    }

    /**
     * Get default promotion (for UI fallback)
     */
    public function default()
    {
        $defaultPromotion = ListingPromotion::getDefault();

        if (!$defaultPromotion) {
            return $this->errorResponse('No default promotion found', 404);
        }

        return $this->successResponse([
            'default_promotion' => [
                'id' => $defaultPromotion->id,
                'name' => $defaultPromotion->name,
                'type' => $defaultPromotion->type,
                'description' => $defaultPromotion->description,
                'price' => $defaultPromotion->price,
                'formatted_price' => $defaultPromotion->formatted_price,
                'currency' => $defaultPromotion->currency,
                'features' => $defaultPromotion->features,
                'visibility_benefits' => $defaultPromotion->visibility_benefits,
                'visibility_summary' => $defaultPromotion->visibility_summary,
                'duration_days' => $defaultPromotion->getDurationInDays(),
                'is_sponsored' => $defaultPromotion->isSponsored(),
                'is_free' => $defaultPromotion->isFree(),
            ]
        ]);
    }

    /**
     * Compare promotions (for UI comparison table)
     */
    public function compare()
    {
        $promotions = ListingPromotion::getActivePromotions();

        // Get all unique features across all promotions
        $allFeatures = collect();
        $allBenefits = collect();

        foreach ($promotions as $promotion) {
            if ($promotion->features) {
                $allFeatures = $allFeatures->merge($promotion->features);
            }
            if ($promotion->visibility_benefits) {
                $allBenefits = $allBenefits->merge(array_keys($promotion->visibility_benefits));
            }
        }

        $uniqueFeatures = $allFeatures->unique()->values()->toArray();
        $uniqueBenefits = $allBenefits->unique()->values()->toArray();

        return $this->successResponse([
            'comparison' => [
                'promotions' => $promotions->map(function ($promotion) use ($uniqueFeatures, $uniqueBenefits) {
                    return [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'type' => $promotion->type,
                        'price' => $promotion->price,
                        'formatted_price' => $promotion->formatted_price,
                        'currency' => $promotion->currency,
                        'duration_days' => $promotion->getDurationInDays(),
                        'features' => $promotion->features ?? [],
                        'visibility_benefits' => $promotion->visibility_benefits ?? [],
                        'is_sponsored' => $promotion->isSponsored(),
                        'is_free' => $promotion->isFree(),
                        'priority_order' => $promotion->priority_order,
                    ];
                }),
                'all_features' => $uniqueFeatures,
                'all_benefits' => $uniqueBenefits,
            ]
        ]);
    }
}
