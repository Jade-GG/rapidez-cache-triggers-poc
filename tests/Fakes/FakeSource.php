<?php

declare(strict_types=1);

namespace Rapidez\RapidezCacheTriggersPoc\Tests\Fakes;

use Closure;
use Illuminate\Support\Collection;
use Rapidez\RapidezCacheTriggersPoc\Sources\Source;
use Throwable;

class FakeSource implements Source
{
    /**
     * @param array<string, array<int, int>>|Closure(mixed, mixed): array<string, array<int, int>> $changes
     */
    public function __construct(
        public mixed $state = 1,
        public array|Closure $changes = [],
        public bool $missed = false,
        public ?Throwable $stateException = null,
        public ?Throwable $changedException = null,
    ) {}

    public function getState(mixed $previous): mixed
    {
        if ($this->stateException) {
            throw $this->stateException;
        }

        return $this->state;
    }

    public function hasMissedChanges(mixed $from, mixed $to): bool
    {
        return $this->missed;
    }

    public function getChanged(mixed $from, mixed $to): Collection
    {
        if ($this->changedException) {
            throw $this->changedException;
        }

        $changes = $this->changes instanceof Closure ? ($this->changes)($from, $to) : $this->changes;

        return collect($changes)->map(fn (array $ids): Collection => collect($ids));
    }
}
