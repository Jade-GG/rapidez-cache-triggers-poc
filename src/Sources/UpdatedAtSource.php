<?php

namespace Rapidez\RapidezCacheTriggersPoc\Sources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

abstract class UpdatedAtSource implements Source
{
    protected string $type;

    abstract protected function query(): Builder;

    public function getState(mixed $previous): string
    {
        return DB::scalar('SELECT NOW()');
    }

    public function hasMissedChanges(mixed $from, mixed $to): bool
    {
        return false;
    }

    public function getChanged(mixed $from, mixed $to): Collection
    {
        $query = $this->query();

        return collect([$this->type => $query->where('updated_at', '>=', $from)->pluck($query->getModel()->getKeyName())]);
    }
}
