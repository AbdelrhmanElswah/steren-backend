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
            $model = $this->model::where('odoo_id', $odooId)->firstOrFail();
            $model->update($data);
            
            $this->errorTracker->trackPerformance(
                'updateByOdooId_' . class_basename($this->model),
                microtime(true) - $startTime,
                ['model' => class_basename($this->model), 'odoo_id' => $odooId, 'data_keys' => array_keys($data)]
            );
            
            return $model->fresh();
        } catch (ModelNotFoundException $e) {
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'updateByOdooId',
                'model' => class_basename($this->model),
                'odoo_id' => $odooId,
                'data' => $data
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
            $model = $this->model::where('odoo_id', $odooId)->firstOrFail();
            $result = $model->delete();
            
            $this->errorTracker->trackPerformance(
                'deleteByOdooId_' . class_basename($this->model),
                microtime(true) - $startTime,
                ['model' => class_basename($this->model), 'odoo_id' => $odooId]
            );
            
            return $result;
        } catch (ModelNotFoundException $e) {
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'deleteByOdooId',
                'model' => class_basename($this->model),
                'odoo_id' => $odooId
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

        $model = $this->model::withTrashed()->where('odoo_id', $odooId)->firstOrFail();
        $model->restore();
        return $model;
    }
}
