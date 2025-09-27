<?php

namespace App\Services\Odoo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class OdooCrudService
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function create(array $data): Model
    {
        return $this->model::create($data);
    }

    public function update(int $id, array $data): Model
    {
        $model = $this->model::findOrFail($id);
        $model->update($data);
        return $model->fresh();
    }

    public function updateByOdooId(string $odooId, array $data): Model
    {
        $model = $this->model::where('odoo_id', $odooId)->firstOrFail();
        $model->update($data);
        return $model->fresh();
    }

    public function delete(int $id): bool
    {
        $model = $this->model::findOrFail($id);
        return $model->delete();
    }

    public function deleteByOdooId(string $odooId): bool
    {
        $model = $this->model::where('odoo_id', $odooId)->firstOrFail();
        return $model->delete();
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
