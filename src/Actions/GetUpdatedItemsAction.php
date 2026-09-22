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
    /**
     *  @param array<string> $triggers
     */
    public function onlyOn(array $triggers): GetUpdatedItemsAction
    {
        $this->triggers = $triggers;

        return $this;
    }
    /**
     *  @param array<string> $triggers
     */
    public function exceptOn(array $triggers): GetUpdatedItemsAction
    {
        $this->exceptTriggers = $triggers;

        return $this;
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
            ->pluck('entity_id')
            ->unique()
            ->values();
    }
}
