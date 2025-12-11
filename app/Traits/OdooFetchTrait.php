<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

trait OdooFetchTrait
{
    use ApiResponse;

    /**
     * Generic method to fetch any model data from regular database for user end
     */
    public function fetchModelData(string $modelClass, string $resourceName, Request $request, array $options = []): JsonResponse
    {
        try {
            // Default options
            $defaultOptions = [
                'with' => [], // Relationships to eager load
                'filters' => [], // Custom filters to apply
                'orderBy' => 'name', // Field to order by
                'orderDirection' => 'asc', // Order direction
                'perPage' => null, // Pagination (null = no pagination)
                'customFields' => [], // Additional fields to include
                'customFormatter' => null, // Custom formatter function
            ];

            $options = array_merge($defaultOptions, $options);

            $query = $modelClass::query();

            // Add relationships if provided
            if (!empty($options['with'])) {
                $query->with($options['with']);
            }

            // Apply custom filters
            foreach ($options['filters'] as $filter) {
                if (isset($filter['field'], $filter['operator'], $filter['value'])) {
                    $query->where($filter['field'], $filter['operator'], $filter['value']);
                }
            }

            // Apply request-based filters
            $this->applyRequestFilters($query, $request, $modelClass);

            // Apply ordering
            $query->orderBy($options['orderBy'], $options['orderDirection']);

            // Handle pagination
            if ($options['perPage']) {
                $items = $query->paginate($options['perPage']);
                $formattedItems = $this->formatItems($items->items(), $options);
                
                return $this->successResponse([
                    $resourceName => $formattedItems,
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'message' => ucfirst($resourceName) . ' fetched successfully'
                ], ucfirst($resourceName) . ' retrieved successfully');
            } else {
                $items = $query->get();
                $formattedItems = $this->formatItems($items, $options);

                return $this->successResponse([
                    $resourceName => $formattedItems,
                    'total' => $items->count(),
                    'message' => ucfirst($resourceName) . ' fetched successfully'
                ], ucfirst($resourceName) . ' retrieved successfully');
            }

        } catch (\Exception $e) {
            Log::error('OdooFetchTrait: Error fetching model data', [
                'model' => $modelClass,
                'resource' => $resourceName,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse("Failed to fetch {$resourceName}: " . $e->getMessage(), 500);
        }
    }

    /**
     * Apply common request-based filters
     */
    protected function applyRequestFilters($query, Request $request, string $modelClass): void
    {
        // Common filters that work across models
        $commonFilters = [
            'search' => function($query, $value) {
                $query->where('name', 'like', "%{$value}%");
            },
            'id' => function($query, $value) {
                $query->where('id', $value);
            },
            'odoo_id' => function($query, $value) {
                $query->where('odoo_id', $value);
            },
        ];

        // Model-specific filters
        $modelFilters = $this->getModelSpecificFilters($modelClass);

        $allFilters = array_merge($commonFilters, $modelFilters);

        foreach ($allFilters as $param => $filter) {
            if ($request->filled($param)) {
                $filter($query, $request->get($param));
            }
        }
    }

    /**
     * Get model-specific filters
     */
    protected function getModelSpecificFilters(string $modelClass): array
    {
        $filters = [];

        switch ($modelClass) {
            case \App\Models\CarModel::class:
                $filters['brand_id'] = function($query, $value) {
                    $query->where('car_brand_id', $value);
                };
                break;
            
            case \App\Models\CarListing::class:
                $filters['brand_id'] = function($query, $value) {
                    $query->where('car_brand_id', $value);
                };
                $filters['model_id'] = function($query, $value) {
                    $query->where('car_model_id', $value);
                };
                $filters['city_id'] = function($query, $value) {
                    $query->where('city_id', $value);
                };
                break;
        }

        return $filters;
    }

    /**
     * Format items for response
     */
    protected function formatItems($items, array $options): array
    {
        return collect($items)->map(function ($item) use ($options) {
            // Use custom formatter if provided
            if ($options['customFormatter'] && is_callable($options['customFormatter'])) {
                return $options['customFormatter']($item);
            }

            // Default formatter
            return $this->defaultItemFormatter($item, $options);
        })->toArray();
    }

    /**
     * Default item formatter
     */
    protected function defaultItemFormatter($item, array $options): array
    {
        $data = [
            'id' => $item->id,
            'name' => $item->name ?? null,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];

        // Add odoo_id if it exists
        if (isset($item->odoo_id)) {
            $data['odoo_id'] = $item->odoo_id;
        }

        // Add media if it exists
        if ($item->relationLoaded('media') && $item->media) {
            $data['logo_url'] = $item->media->media_link;
        }

        // Add custom fields
        foreach ($options['customFields'] as $field) {
            if (isset($item->$field)) {
                $data[$field] = $item->$field;
            }
        }

        // Add relationship data
        $this->addRelationshipData($data, $item, $options);

        return $data;
    }

    /**
     * Add relationship data to formatted item
     */
    protected function addRelationshipData(array &$data, $item, array $options): void
    {
        // Add loaded relationships
        foreach ($options['with'] as $relation) {
            if ($item->relationLoaded($relation) && $item->$relation) {
                if (is_object($item->$relation)) {
                    // Single relationship
                    $data[$relation] = [
                        'id' => $item->$relation->id,
                        'name' => $item->$relation->name ?? null,
                    ];
                } elseif (is_iterable($item->$relation)) {
                    // Collection relationship
                    $data[$relation] = $item->$relation->map(function ($relatedItem) {
                        return [
                            'id' => $relatedItem->id,
                            'name' => $relatedItem->name ?? null,
                        ];
                    })->toArray();
                }
            }
        }
    }

    /**
     * Convenience methods for common models (optional - for backward compatibility)
     */
    public function fetchBrands(Request $request): JsonResponse
    {
        return $this->fetchModelData(
            \App\Models\CarBrand::class,
            'brands',
            $request,
            [
                'with' => ['media'],
                'customFields' => ['logo_url'],
            ]
        );
    }

    public function fetchCities(Request $request): JsonResponse
    {
        return $this->fetchModelData(
            \App\Models\City::class,
            'cities',
            $request
        );
    }

    public function fetchCarModels(Request $request): JsonResponse
    {
        return $this->fetchModelData(
            \App\Models\CarModel::class,
            'car_models',
            $request,
            [
                'with' => ['carBrand'],
                'customFields' => ['car_brand_id'],
            ]
        );
    }

    public function fetchStudios(Request $request): JsonResponse
    {
        return $this->fetchModelData(
            \App\Models\Studio::class,
            'studios',
            $request
        );
    }
}