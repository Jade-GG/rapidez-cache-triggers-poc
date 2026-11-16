<?php

namespace Rapidez\RapidezCacheTriggersPoc\Transforms;

use Illuminate\Support\Collection;

class AddCategoryParents
{
    /**
     * @param Collection<int, int> $categories
     * @return Collection<int, int>
     */
    public function handle(Collection $categories): Collection
    {
        return $categories->merge(config('rapidez.models.category')::withoutGlobalScopes()
            ->whereIn('entity_id', $categories)
            ->pluck('path')
            ->flatMap(fn (string $path) => array_slice(explode('/', $path), 2))
            ->map(fn (string $id) => (int) $id));
    }
}
