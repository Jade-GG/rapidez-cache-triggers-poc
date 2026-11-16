<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models\Changelogs;

use Illuminate\Support\Collection;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelog;

class StockChangelog extends Changelog
{
    protected string $view = 'cataloginventory_stock';

    /**
     * @return Collection<string, Collection<int, int>>
     */
    public function getChanged(mixed $from, mixed $to): Collection
    {
        return collect(['products' => $this->getChangedIds($from, $to)]);
    }
}
