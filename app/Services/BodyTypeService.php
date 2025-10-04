<?php

namespace App\Services;

use App\Models\BodyType;
use App\Http\Resources\BodyTypeResource;
use App\Http\Resources\BodyTypeOptionResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BodyTypeService
{
    public function getAllBodyTypes(): Collection
    {
        return BodyType::with('medias')->orderBy('name')->get();
    }

    public function getBodyTypeById(int $id): ?BodyType
    {
        return BodyType::with('medias')->find($id);
    }

    public function getBodyTypeByOdooId(string $odooId): ?BodyType
    {
        return BodyType::with('medias')->where('odoo_id', $odooId)->first();
    }

    public function getBodyTypeOptions(): Collection
    {
        return $this->getAllBodyTypes();
    }

    public function toResourceCollection(Collection $bodyTypes): AnonymousResourceCollection
    {
        return BodyTypeResource::collection($bodyTypes);
    }

    public function toOptionResourceCollection(Collection $bodyTypes): AnonymousResourceCollection
    {
        return BodyTypeOptionResource::collection($bodyTypes);
    }

    public function createBodyType(array $data): BodyType
    {
        return BodyType::create($data);
    }

    public function updateBodyType(BodyType $bodyType, array $data): bool
    {
        return $bodyType->update($data);
    }

    public function deleteBodyType(BodyType $bodyType): bool
    {
        return $bodyType->delete();
    }

    public function searchBodyTypes(string $query): Collection
    {
        return BodyType::with('medias')
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->get();
    }

    public function getBodyTypesPaginated(int $perPage = 15)
    {
        return BodyType::with('medias')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
