<?php

namespace Rapidez\RapidezCacheTriggersPoc\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Rapidez\RapidezCacheTriggersPoc\Exceptions\SourceUnavailableException;
use Rapidez\RapidezCacheTriggersPoc\Sources\Source;
use TorMorten\Eventy\Facades\Eventy;

class GetUpdatedItemsAction
{
    public const STATE_PATH = '/.rapidez-changelog-';

    /**
     * @return array{everything: bool}&array<string, Collection<int, int>|bool>
     */
    public function handle(string $tag = 'default', ?int $threshold = null): array
    {
        $previous = json_decode(Storage::disk('local')->get(static::STATE_PATH . $tag) ?? '', true)['sources'] ?? null;
        $sources = collect(config('rapidez.cache-triggers-poc.sources'))->map(fn (string $class) => app($class));
        $state = $sources->map(fn (Source $source, string $key) => $source->getState($previous[$key] ?? null))->toArray();
        Storage::disk('local')->put(static::STATE_PATH . $tag, json_encode(['sources' => $state]));

        if (! $previous) {
            return ['everything' => true];
        }

        $unavailable = $sources->keys()->filter(fn (string $key) => isset($previous[$key]) && ! isset($state[$key]));
        if ($unavailable->isNotEmpty()) {
            $unavailable->each(fn (string $key) => report(SourceUnavailableException::forSource($key)));

            return ['everything' => true];
        }

        $sources = $sources->filter(fn (Source $source, string $key) => isset($previous[$key], $state[$key]));
        if ($sources->contains(fn (Source $source, string $key) => $source->hasMissedChanges($previous[$key], $state[$key]))) {
            return ['everything' => true];
        }

        $changes = $sources
            ->map(fn (Source $source, string $key) => $source->getChanged($previous[$key], $state[$key]))
            ->reduce(function (Collection $accumulator, Collection $sourceChanges) {
                return $sourceChanges
                    ->map(fn (Collection $ids, string $type) => $accumulator->get($type, collect())->merge($ids))
                    ->union($accumulator);
            }, collect())
            ->map(fn (Collection $ids) => $ids->unique()->values());

        if ($changes->sum->count() > ($threshold ?? config('rapidez.cache-triggers-poc.threshold'))) {
            return ['everything' => true];
        }

        foreach ($changes as $type => $ids) {
            $changes[$type] = Eventy::filter('cache-triggers-poc.changes.' . $type, $ids)->unique()->values();
        }

        return ['everything' => false, ...$changes];
    }
}
