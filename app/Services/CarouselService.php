<?php

namespace App\Services;

use App\Models\Carousel;
use App\Http\Resources\CarouselResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class CarouselService
{
    public function getActiveCarousels(int $limit = null): Collection
    {
        $query = Carousel::with('medias')
            ->active()
            ->withinDateRange()
            ->ordered();

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getAllCarousels(array $filters = []): Collection
    {
        $query = Carousel::with('medias');

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active()->withinDateRange();
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->ordered()->get();
    }

    public function getCarouselByOdooId(string $odooId): ?Carousel
    {
        return Carousel::with('medias')
            ->where('odoo_id', $odooId)
            ->first();
    }

    public function getCarouselById(int $id): ?Carousel
    {
        return Carousel::with('medias')->find($id);
    }

    public function searchCarousels(string $query): Collection
    {
        return Carousel::with('medias')
            ->where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->ordered()
            ->get();
    }

    public function getCarouselsPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Carousel::with('medias');

        // Apply filters
        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active()->withinDateRange();
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->ordered()->paginate($perPage);
    }

    public function createCarousel(array $data): Carousel
    {
        return Carousel::create($data);
    }

    public function updateCarousel(Carousel $carousel, array $data): bool
    {
        return $carousel->update($data);
    }

    public function deleteCarousel(Carousel $carousel): bool
    {
        return $carousel->delete();
    }

    public function restoreCarousel(Carousel $carousel): bool
    {
        return $carousel->restore();
    }

    public function toResourceCollection(Collection $carousels): AnonymousResourceCollection
    {
        return CarouselResource::collection($carousels);
    }

    public function toResource(Carousel $carousel): CarouselResource
    {
        return new CarouselResource($carousel);
    }
}
