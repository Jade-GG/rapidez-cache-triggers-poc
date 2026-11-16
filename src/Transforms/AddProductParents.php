<?php

namespace Rapidez\RapidezCacheTriggersPoc\Transforms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AddProductParents
{
    /**
     * @param Collection<int, int> $products
     * @return Collection<int, int>
     */
    public function handle(Collection $products): Collection
    {
        return $products->merge(config('rapidez.models.product')::withoutGlobalScopes()
            ->whereHas('children', fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('child_id', $products))
            ->pluck('entity_id'));
    }
}
