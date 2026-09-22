<?php

namespace Rapidez\RapidezCacheTriggersPoc\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Rapidez\RapidezCacheTriggersPoc\Models\CategoryChangelog;
use Rapidez\RapidezCacheTriggersPoc\Models\ProductChangelog;

class GetUpdatedItemsAction
{
    protected array $triggers = [];
    protected array $exceptTriggers = [];
    protected ?int $storeId = null;

    /**
     *  @param array<string> $triggers
     */
    public function onlyOn(array $triggers): static
    {
        $clone = clone $this;
        $clone->triggers = $triggers;

        return $clone;
    }

    /**
     *  @param array<string> $triggers
     */
    public function exceptOn(array $triggers): static
    {
        $clone = clone $this;
        $clone->exceptTriggers = $triggers;

        return $clone;
    }

    public function store(int $storeId): static
    {
        $clone = clone $this;
        $clone->storeId = $storeId;

        return $clone;
    }

    /**
     *  @return array<string,Collection<int, int>>
     */
    public function getSince(Carbon $since): array
    {
        return [
            'products' => $this->getModelSince(ProductChangelog::class, $since),
            'categories' => $this->getModelSince(CategoryChangelog::class, $since),
        ];
    }

    /**
     *  @return Collection<int, int>
     */
    protected function getModelSince(string $model, Carbon $since): Collection
    {
        return $model::query()
            ->where('created_at', '>', $since)
            ->when(
                count($this->triggers),
                fn (Builder $query) => $query->whereIn('trigger', $this->triggers),
                fn (Builder $query) => $query->whereNotIn('trigger', $this->exceptTriggers),
            )
            ->when(
                ! is_null($this->storeId),
                fn (Builder $query) => $query->whereIn('store_id', [0, $this->storeId]),
            )
            ->pluck('entity_id')
            ->unique()
            ->values();
    }
}
