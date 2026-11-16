<?php

namespace Rapidez\RapidezCacheTriggersPoc\Sources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttributeSource implements Source
{
    public function getState(mixed $previous): string
    {
        return collect(DB::select('CHECKSUM TABLE eav_attribute, catalog_eav_attribute, eav_attribute_label, eav_entity_attribute'))->pluck('Checksum')->implode(',');
    }

    public function hasMissedChanges(mixed $from, mixed $to): bool
    {
        return $from !== $to;
    }

    public function getChanged(mixed $from, mixed $to): Collection
    {
        return collect();
    }
}
