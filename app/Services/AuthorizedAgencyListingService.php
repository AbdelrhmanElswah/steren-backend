<?php

namespace App\Services;

use App\Models\AuthorizedAgency;
use App\Models\AuthorizedAgencyModel;
use App\Models\CarListing;
use App\Models\DealerStudio;
use Illuminate\Support\Facades\Cache;

class AuthorizedAgencyListingService
{
    public function __construct(private CarListingFormatterService $formatter)
    {
    }

    /**
     * Get listings for an authorized agency with custom structure:
     * - used: same shape as studio listings (formatted listings)
     * - new: models sourced from authorized_agency_models (no trims)
     */
    public function getAgencyListings(int $authorizedAgencyId, ?string $lang = null): array
    {
        $cacheKey = 'authorized_agency_listings_' . $authorizedAgencyId . '_' . ($lang ?: 'default');

        return Cache::remember($cacheKey, 86400, function () use ($authorizedAgencyId, $lang) {
            $agency = AuthorizedAgency::select('id', 'name', 'name_ar', 'name_kr', 'partner_id')->findOrFail($authorizedAgencyId);

            $studioIds = $this->getStudioIdsForAgency($agency->partner_id);

            // Base used listings query (same filters as studio service)
            $usedListings = $this->getUsedListings($studioIds);
            $usedFormatted = $this->formatter->formatListings($usedListings);

            // New models from authorized_agency_models
            $models = $this->getAgencyModels($authorizedAgencyId, $lang);

            return [
                'agency' => [
                    'id' => $agency->id,
                    'name' => $this->getLocalized($agency, 'name', $lang),
                    'partner_id' => $agency->partner_id,
                    'studio_ids' => $studioIds,
                ],
                'new' => [
                    'count' => count($models),
                    'models' => $models,
                ],
                'used' => [
                    'count' => $usedListings->count(),
                    'listings' => $usedFormatted,
                ],
            ];
        });
    }

    private function getStudioIdsForAgency(?string $partnerId): array
    {
        if (!$partnerId) {
            return [];
        }

        $userIds = \App\Models\User::where('partner_id', $partnerId)->pluck('id');
        if ($userIds->isEmpty()) {
            return [];
        }

        return DealerStudio::whereIn('dealer_id', $userIds)->pluck('id')->all();
    }

    private function buildBaseQuery(array $studioIds)
    {
        return CarListing::select('car_listings.*')
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'city:id,name,odoo_id',
                'carCondition:id,name',
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name',
                'driveType:id,name',
                'cylinder:id,name',
                'mileageType:id,name',
                'media' => function ($query) {
                    $query->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->orderBy('order');
                },
                'inspection' => function ($inspectionQuery) {
                    $inspectionQuery->select('id', 'car_listing_id', 'right_side', 'left_side', 'roof', 'front', 'back');
                },
                'user:id,full_name',
                'user.profilePictureMedia' => function ($profileQuery) {
                    $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info')
                        ->where('info', 'profile_picture');
                },
            ])
            ->whereIn('studio_id', $studioIds)
            ->where('status', 'active')
            ->whereNull('sold_at');
    }

    private function getUsedListings(array $studioIds)
    {
        if (empty($studioIds)) {
            return collect();
        }

        return $this->buildBaseQuery($studioIds)
            ->orderByDesc('created_at')
            ->get();
    }

    private function getAgencyModels(int $authorizedAgencyId, ?string $lang = null): array
    {
        $models = AuthorizedAgencyModel::with([
                'carModel:id,name,name_ar,name_kr,car_brand_id',
                'carModel.brand:id,name,name_ar,name_kr',
                'outerImage'
            ])
            ->where('authorized_agency_id', $authorizedAgencyId)
            ->get();

        return $models->map(function ($model) use ($lang) {
            $outerUrl = null;
            if ($model->outerImage && $model->outerImage->path_file_name) {
                $outerUrl = $model->outerImage->getS3PublicUrl($model->outerImage->path_file_name);
            }

            return [
                'id' => $model->id,
                'car_model_id' => $model->car_model_id,
                'model_name' => $this->getLocalized($model->carModel, 'name', $lang),
                'brand_name' => $this->getLocalized($model->carModel?->brand, 'name', $lang),
                'price_start_from' => $model->price_start_from,
                'price_currency' => $model->price_currency,
                'outer_image' => $outerUrl,
            ];
        })->values()->toArray();
    }

    private function getLocalized($model, string $field, ?string $lang)
    {
        if (!$model) {
            return null;
        }

        $lang = $lang ? strtolower($lang) : null;

        return match ($lang) {
            'ar' => $model->{$field . '_ar'} ?? $model->{$field} ?? null,
            'kr', 'ku', 'ckb' => $model->{$field . '_kr'} ?? $model->{$field} ?? null,
            default => $model->{$field} ?? null,
        };
    }
}


