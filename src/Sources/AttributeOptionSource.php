<?php

namespace Rapidez\RapidezCacheTriggersPoc\Sources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Rapidez\RapidezCacheTriggersPoc\Models\AttributeOption;

class AttributeOptionSource implements Source
{
    /**
     * @return array{checksum: string, hashes: array<int, int>}
     */
    public function getState(mixed $previous): array
    {
        $checksum = collect(DB::select('CHECKSUM TABLE eav_attribute_option, eav_attribute_option_value, eav_attribute_option_swatch'))->pluck('Checksum')->implode(',');

        return [
            'checksum' => $checksum,
            'hashes' => $checksum === ($previous['checksum'] ?? null) ? $previous['hashes'] : AttributeOption::getHashes(),
        ];
    }

    public function hasMissedChanges(mixed $from, mixed $to): bool
    {
        return count(array_diff_key($from['hashes'], $to['hashes'])) > 0;
    }

    public function getChanged(mixed $from, mixed $to): Collection
    {
        return collect(['products' => AttributeOption::getProducts(array_keys(array_diff_assoc($to['hashes'], $from['hashes'])))]);
    }
}
