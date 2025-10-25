<?php

namespace App\Services\Odoo;

use App\Models\CarModel;
use App\Models\CarBrand;
use Illuminate\Database\Eloquent\Collection;

class CarModelService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarModel());
    }

    public function create(array $data): CarModel
    {
        if (isset($data['car_brand_id'])) {
            $data['car_brand_id'] = $this->convertBrandOdooIdToLaravelId($data['car_brand_id']);
        }
        
        $model = parent::create($data);
        return $model;
    }

    public function updateByOdooId(string $odooId, array $data): CarModel
    {
        if (isset($data['car_brand_id'])) {
            $data['car_brand_id'] = $this->convertBrandOdooIdToLaravelId($data['car_brand_id']);
        }
        
        $model = parent::updateByOdooId($odooId, $data);
        return $model;
    }

    public function getBrandsWithModels(array $filters = []): Collection
    {
        return CarBrand::with(['carModels' => function ($query) use ($filters) {
            if (!empty($filters['search'])) {
                $query->where('name', 'like', '%' . $filters['search'] . '%');
            }
            if (!empty($filters['id'])) {
                $query->where('id', $filters['id']);
            }
            if (!empty($filters['odoo_id'])) {
                $query->where('odoo_id', $filters['odoo_id']);
            }
            $query->orderBy('name')->limit(50);
        }, 'media'])
        ->when(!empty($filters['brand_id']), function ($query) use ($filters) {
            $query->where('id', $filters['brand_id']);
        })
        ->when(!empty($filters['search']), function ($query) use ($filters) {
            $query->whereHas('carModels', function ($modelQuery) use ($filters) {
                $modelQuery->where('name', 'like', '%' . $filters['search'] . '%');
            });
        })
        ->orderBy('name')
        ->limit(50)
        ->get();
    }


    private function convertBrandOdooIdToLaravelId($brandId): int
    {
        $brand = CarBrand::where('odoo_id', $brandId)->first();
        if ($brand) {
            return $brand->id;
        }
        
        if (is_numeric($brandId)) {
            $brand = CarBrand::where('id', $brandId)->first();
            if ($brand) {
                return (int) $brandId;
            }
        }
        
        throw new \Exception("Car brand with ID/Odoo ID '{$brandId}' not found");
    }
}
