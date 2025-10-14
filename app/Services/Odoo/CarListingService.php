<?php

namespace App\Services\Odoo;

use App\Models\CarListing;
use App\Models\User;
use App\Services\OdooService;
use Illuminate\Support\Facades\Log;

class CarListingService extends OdooCrudService
{
    protected OdooService $odooService;

    public function __construct()
    {
        parent::__construct(new CarListing());
        $this->odooService = app(OdooService::class);
    }

    public function create(array $data): CarListing
    {
        $carListing = parent::create($data);

        $this->syncToOdoo($carListing);

        return $carListing;
    }

    private function syncToOdoo(CarListing $carListing): void
    {
        try {
            $user = User::find($carListing->user_id);
            if (!$user || !$user->partner_id) {
                Log::warning('Cannot sync to Odoo: User or partner_id not found', [
                    'car_listing_id' => $carListing->id,
                    'user_id' => $carListing->user_id
                ]);
                return;
            }

            $odooPayload = $this->prepareOdooPayload($carListing, $user);

            $response = $this->odooService->createListing($odooPayload);

            Log::info('Odoo API response received', [
                'car_listing_id' => $carListing->id,
                'response' => $response
            ]);

            if ($response['success']) {
                $odooId = $response['odoo_listing_id'];
                $carListing->update(['odoo_id' => $odooId]);
                
                Log::info('Car listing synced to Odoo successfully', [
                    'car_listing_id' => $carListing->id,
                    'odoo_listing_id' => $odooId,
                    'updated_record' => $carListing->fresh()->toArray()
                ]);
            } else {
                Log::error('Failed to sync car listing to Odoo', [
                    'car_listing_id' => $carListing->id,
                    'odoo_response' => $response
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception during Odoo sync', [
                'car_listing_id' => $carListing->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Prepare payload for Odoo API
     */
    private function prepareOdooPayload(CarListing $carListing, User $user): array
    {
        return [
            'partner_id' => $user->partner_id,
            'car_info' => [
                'vin' => $carListing->vin,
                'brand' => $this->getCarBrandOdooId($carListing->car_brand_id),
                'model' => $this->getCarModelOdooId($carListing->car_model_id),
                'condition' => $carListing->condition_type ?? 'used',
                'year' => $carListing->year,
                'gcc_import' => $carListing->gcc_import ? 'GCC-' . $carListing->gcc_import : null,
                'body_type' => $this->getBodyTypeOdooId($carListing->car_body_type_id),
                'engine_size' => $carListing->engine_size,
                'fuel_type' => $this->getFuelTypeName($carListing->fuel_type_id),
                'cylinders' => $this->getCylinderCount($carListing->cylinder_id),
                'transmission' => $this->getTransmissionName($carListing->transmission_id),
                'seating_capacity' => $carListing->seating_capacity,
                'exterior_colour' => $this->getExteriorColorName($carListing->exterior_color_id),
                'warranty' => $carListing->has_warranty ? 'yes' : 'no',
                'drive_type' => $this->getDriveTypeName($carListing->drive_type_id),
                'mileage_type' => $this->getMileageTypeName($carListing->mileage_type_id),
                'mileage' => $carListing->mileage,
                'plate_status' => $carListing->plate_status,
                'inspection' => [
                    'right_side' => false,
                    'left_side' => false,
                    'roof' => false,
                    'front' => false,
                    'back' => false
                ],
                'inspection_file_name' => null
            ],
            'price_currency' => $carListing->price_currency ?? 'USD',
            'price' => $carListing->price,
            'seller_info' => [
                'type' => $carListing->is_verified ? 'verified_seller' : 'regular_seller'
            ],
            'title' => $carListing->title,
            'description' => $carListing->description,
            'location' => $this->getLocationOdooId($carListing->location_id),
            'images' => []
        ];
    }

    /**
     * Helper methods to get related data names
     */
    private function getFuelTypeName($fuelTypeId): ?string
    {
        if (!$fuelTypeId) return null;
        $fuelType = \App\Models\FuelType::find($fuelTypeId);
        return $fuelType ? strtolower($fuelType->name) : null;
    }

    private function getCylinderCount($cylinderId): ?int
    {
        if (!$cylinderId) return null;
        $cylinder = \App\Models\CylinderOption::find($cylinderId);
        return $cylinder ? (int)$cylinder->name : null;
    }

    private function getTransmissionName($transmissionId): ?string
    {
        if (!$transmissionId) return null;
        $transmission = \App\Models\Transmission::find($transmissionId);
        return $transmission ? strtolower($transmission->name) : null;
    }

    private function getExteriorColorName($colorId): ?string
    {
        if (!$colorId) return null;
        $color = \App\Models\ExteriorColor::find($colorId);
        return $color ? $color->name : null;
    }

    private function getDriveTypeName($driveTypeId): ?string
    {
        if (!$driveTypeId) return null;
        $driveType = \App\Models\DriveType::find($driveTypeId);
        return $driveType ? strtolower($driveType->name) : null;
    }

    private function getMileageTypeName($mileageTypeId): ?string
    {
        if (!$mileageTypeId) return 'KM';
        $mileageType = \App\Models\MileageType::find($mileageTypeId);
        return $mileageType ? strtoupper($mileageType->name) : 'KM';
    }

    private function getBodyTypeOdooId($bodyTypeId): ?int
    {
        if (!$bodyTypeId) return null;
        $bodyType = \App\Models\BodyType::find($bodyTypeId);
        return $bodyType ? (int)$bodyType->odoo_id : null;
    }

    private function getCarBrandOdooId($brandId): ?int
    {
        if (!$brandId) return null;
        $brand = \App\Models\CarBrand::find($brandId);
        return $brand ? (int)$brand->odoo_id : null;
    }

    private function getCarModelOdooId($modelId): ?int
    {
        if (!$modelId) return null;
        $model = \App\Models\CarModel::find($modelId);
        return $model ? (int)$model->odoo_id : null;
    }

    private function getLocationOdooId($locationId): ?int
    {
        if (!$locationId) return null;
        $location = \App\Models\Location::find($locationId);
        if ($location && $location->odoo_id) {
            return (int)$location->odoo_id;
        }
        
        $city = \App\Models\City::where('name', 'like', '%' . $location->name . '%')->first();
        return $city ? (int)$city->odoo_id : null;
    }
}
