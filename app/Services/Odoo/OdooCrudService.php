<?php

namespace App\Services\Odoo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Exceptions\OdooModelNotFoundException;
use App\Exceptions\OdooDatabaseException;
use App\Services\ErrorTrackingService;
use Illuminate\Support\Facades\Log;

abstract class OdooCrudService
{
    protected Model $model;
    protected ErrorTrackingService $errorTracker;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->errorTracker = app(ErrorTrackingService::class);
    }

    public function create(array $data): Model
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->model::create($data);
            
            $this->errorTracker->trackPerformance(
                'create_' . class_basename($this->model),
                microtime(true) - $startTime,
                ['model' => class_basename($this->model), 'data_keys' => array_keys($data)]
            );
            
            return $result;
        } catch (QueryException $e) {
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'create',
                'model' => class_basename($this->model),
                'data' => $data
            ]);
            
            throw new OdooDatabaseException(
                'Failed to create ' . class_basename($this->model) . ' record',
                'create',
                ['model' => class_basename($this->model), 'data' => $data],
                0,
                $e
            );
        }
    }

    public function update(int $id, array $data): Model
    {
        $model = $this->model::findOrFail($id);
        $model->update($data);
        return $model->fresh();
    }

    public function updateByOdooId(string $odooId, array $data): Model
    {
        $startTime = microtime(true);
        
        try {
            // Try to find by odoo_id - handle both string and integer comparisons
            // First try as string (most common case)
            $model = $this->model::where('odoo_id', (string) $odooId)->first();
            
            // If not found and odooId is numeric, try as integer (in case DB stores as integer)
            if (!$model && is_numeric($odooId)) {
                $model = $this->model::where('odoo_id', (int) $odooId)->first();
            }
            
            if (!$model) {
                throw new ModelNotFoundException();
            }
            
            $model->update($data);
            
            $this->errorTracker->trackPerformance(
                'updateByOdooId_' . class_basename($this->model),
                microtime(true) - $startTime,
                ['model' => class_basename($this->model), 'odoo_id' => $odooId, 'data_keys' => array_keys($data)]
            );
            
            return $model->fresh();
        } catch (ModelNotFoundException $e) {
            // Log available odoo_id values for debugging
            $availableOdooIds = $this->model::whereNotNull('odoo_id')
                ->pluck('odoo_id')
                ->take(10)
                ->toArray();
            
            Log::warning('OdooCrudService: Model not found by odoo_id', [
                'model' => class_basename($this->model),
                'requested_odoo_id' => $odooId,
                'available_odoo_ids_sample' => $availableOdooIds,
                'total_records_with_odoo_id' => $this->model::whereNotNull('odoo_id')->count(),
            ]);
            
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'updateByOdooId',
                'model' => class_basename($this->model),
                'odoo_id' => $odooId,
                'data' => $data,
                'available_odoo_ids_sample' => $availableOdooIds,
            ]);
            
            throw new OdooModelNotFoundException(
                class_basename($this->model) . ' with Odoo ID "' . $odooId . '" not found',
                class_basename($this->model),
                $odooId,
                ['operation' => 'update', 'data' => $data],
                0,
                $e
            );
        } catch (QueryException $e) {
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'updateByOdooId',
                'model' => class_basename($this->model),
                'odoo_id' => $odooId,
                'data' => $data
            ]);
            
            throw new OdooDatabaseException(
                'Failed to update ' . class_basename($this->model) . ' record with Odoo ID "' . $odooId . '"',
                'updateByOdooId',
                ['model' => class_basename($this->model), 'odoo_id' => $odooId, 'data' => $data],
                0,
                $e
            );
        }
    }

    public function delete(int $id): bool
    {
        $model = $this->model::findOrFail($id);
        return $model->delete();
    }

    public function deleteByOdooId(string $odooId): bool
    {
        $startTime = microtime(true);
        
        try {
            // Try to find by odoo_id - handle both string and integer comparisons
            // First try as string (most common case)
            $model = $this->model::where('odoo_id', (string) $odooId)->first();
            
            // If not found and odooId is numeric, try as integer (in case DB stores as integer)
            if (!$model && is_numeric($odooId)) {
                $model = $this->model::where('odoo_id', (int) $odooId)->first();
            }
            
            if (!$model) {
                throw new ModelNotFoundException();
            }
            
            $result = $model->delete();
            
            $this->errorTracker->trackPerformance(
                'deleteByOdooId_' . class_basename($this->model),
                microtime(true) - $startTime,
                ['model' => class_basename($this->model), 'odoo_id' => $odooId]
            );
            
            return $result;
        } catch (ModelNotFoundException $e) {
            // Log available odoo_id values for debugging
            $availableOdooIds = $this->model::whereNotNull('odoo_id')
                ->pluck('odoo_id')
                ->take(10)
                ->toArray();
            
            Log::warning('OdooCrudService: Model not found by odoo_id for delete', [
                'model' => class_basename($this->model),
                'requested_odoo_id' => $odooId,
                'available_odoo_ids_sample' => $availableOdooIds,
            ]);
            
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'deleteByOdooId',
                'model' => class_basename($this->model),
                'odoo_id' => $odooId,
                'available_odoo_ids_sample' => $availableOdooIds,
            ]);
            
            throw new OdooModelNotFoundException(
                class_basename($this->model) . ' with Odoo ID "' . $odooId . '" not found',
                class_basename($this->model),
                $odooId,
                ['operation' => 'delete'],
                0,
                $e
            );
        } catch (QueryException $e) {
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'deleteByOdooId',
                'model' => class_basename($this->model),
                'odoo_id' => $odooId
            ]);
            
            throw new OdooDatabaseException(
                'Failed to delete ' . class_basename($this->model) . ' record with Odoo ID "' . $odooId . '"',
                'deleteByOdooId',
                ['model' => class_basename($this->model), 'odoo_id' => $odooId],
                0,
                $e
            );
        }
    }

    public function restore(int $id): Model
    {
        if (!in_array(SoftDeletes::class, class_uses_recursive($this->model))) {
            throw new \Exception('Model does not support soft deletes');
        }

        $model = $this->model::withTrashed()->findOrFail($id);
        $model->restore();
        return $model;
    }

    public function restoreByOdooId(string $odooId): Model
    {
        if (!in_array(SoftDeletes::class, class_uses_recursive($this->model))) {
            throw new \Exception('Model does not support soft deletes');
        }

        // Try to find by odoo_id - handle both string and integer comparisons
        // First try as string (most common case)
        $model = $this->model::withTrashed()->where('odoo_id', (string) $odooId)->first();
        
        // If not found and odooId is numeric, try as integer (in case DB stores as integer)
        if (!$model && is_numeric($odooId)) {
            $model = $this->model::withTrashed()->where('odoo_id', (int) $odooId)->first();
        }
        
        if (!$model) {
            throw new ModelNotFoundException();
        }
        
        $model->restore();
        return $model;
    }
}
