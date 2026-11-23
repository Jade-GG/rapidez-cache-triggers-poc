<?php

namespace Rapidez\RapidezCacheTriggersPoc\Sources;

use Illuminate\Support\Collection;

interface Source
{
    public function getState(mixed $previous): mixed;

    public function hasMissedChanges(mixed $from, mixed $to): bool;

    /**
     * @return Collection<string, Collection<int, int>>
     */
    public function getChanged(mixed $from, mixed $to): Collection;
}
