<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models\Changelogs;

use Illuminate\Support\Collection;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelog;

class CatalogsearchFulltextChangelog extends Changelog
{
    protected string $view = 'catalogsearch_fulltext';

    /**
     * @return Collection<string, Collection<int, int>>
     */
    public function getChanged(mixed $from, mixed $to): Collection
    {
        return collect(['products' => $this->getChangedIds($from, $to)]);
    }
}
