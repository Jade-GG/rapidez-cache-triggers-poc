<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Rapidez\Core\Models\Model;
use Rapidez\RapidezCacheTriggersPoc\Sources\Source;

abstract class Changelog extends Model implements Source
{
    public $timestamps = false;

    protected $primaryKey = 'version_id';

    protected string $view;

    public function getTable()
    {
        return $this->view.'_cl';
    }

    public function getState(mixed $previous): ?int
    {
        if (! Schema::hasTable($this->getTable())) {
            return null;
        }

        return (int) static::max('version_id');
    }

    public function hasMissedChanges(mixed $from, mixed $to): bool
    {
        if ($from > $to) {
            return true;
        }

        if ($from === $to) {
            return false;
        }

        return $from > 0 && static::where('version_id', $from)->doesntExist();
    }

    /**
     * @return Collection<int, int>
     */
    protected function getChangedIds(int $from, int $to): Collection
    {
        return static::where('version_id', '>', $from)
            ->where('version_id', '<=', $to)
            ->distinct()
            ->pluck('entity_id');
    }
}
