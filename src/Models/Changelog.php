<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Rapidez\Core\Models\Model;

abstract class Changelog extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'version_id';

    protected string $view;

    public function getTable()
    {
        return $this->view.'_cl';
    }

    public function getVersion(): ?int
    {
        if (! Schema::hasTable($this->getTable())) {
            return null;
        }

        return (int) static::max('version_id');
    }

    public function hasMissedChanges(int $from, int $to): bool
    {
        if ($from > $to) {
            return true;
        }

        if ($from === $to) {
            return false;
        }

        return static::where('version_id', '>', $from)->min('version_id') > $from + 1;
    }

    /**
     * @return Collection<int, int>
     */
    public function getChangedIds(int $from, int $to): Collection
    {
        return static::where('version_id', '>', $from)
            ->where('version_id', '<=', $to)
            ->distinct()
            ->pluck('entity_id');
    }
}
