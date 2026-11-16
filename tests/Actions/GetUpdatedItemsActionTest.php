<?php

declare(strict_types=1);

namespace Rapidez\RapidezCacheTriggersPoc\Tests\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Rapidez\RapidezCacheTriggersPoc\Actions\GetUpdatedItemsAction;
use Rapidez\RapidezCacheTriggersPoc\Tests\Fakes\FakeSource;
use Rapidez\RapidezCacheTriggersPoc\Tests\TestCase;
use RuntimeException;
use TorMorten\Eventy\Facades\Eventy;

final class GetUpdatedItemsActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['rapidez.cache-triggers-poc.sources' => []]);
    }

    protected function addSource(string $key, FakeSource $source): FakeSource
    {
        $this->app->instance('fake.' . $key, $source);
        config(['rapidez.cache-triggers-poc.sources.' . $key => 'fake.' . $key]);

        return $source;
    }

    /**
     * @return array<string, mixed>
     */
    protected function handle(string $tag = 'default', ?int $threshold = null): array
    {
        return collect(resolve(GetUpdatedItemsAction::class)->handle($tag, $threshold))
            ->map(fn (mixed $value): mixed => $value instanceof Collection ? $value->all() : $value)
            ->all();
    }

    #[Test]
    public function it_stores_a_baseline_per_tag(): void
    {
        $this->addSource('one', new FakeSource(changes: ['products' => [1]]));

        $this->assertEquals(['everything' => true], $this->handle('first'));
        $this->assertFalse($this->handle('first')['everything']);
        $this->assertTrue($this->handle('second')['everything']);
    }

    #[Test]
    public function it_merges_and_filters_the_changes_per_type(): void
    {
        Eventy::addFilter('cache-triggers-poc.changes.pages', fn (Collection $ids): Collection => $ids->merge([8, 9]));
        $this->addSource('one', new FakeSource(changes: ['products' => [1, 2], 'categories' => [5], 'pages' => []]));
        $this->addSource('two', new FakeSource(changes: ['products' => [2, 3]]));
        $this->handle();

        $changes = $this->handle(threshold: 4);

        $this->assertFalse($changes['everything']);
        $this->assertEqualsCanonicalizing([1, 2, 3], $changes['products']);
        $this->assertEquals([5], $changes['categories']);
        $this->assertEquals([8, 9], $changes['pages']);
    }

    #[Test]
    public function it_returns_everything_for_missed_changes_or_above_the_threshold(): void
    {
        config(['rapidez.cache-triggers-poc.threshold' => 2]);
        $source = $this->addSource('one', new FakeSource(changes: ['products' => [1, 2], 'categories' => [3]]));
        $this->handle();

        $this->assertTrue($this->handle()['everything']);
        $this->assertFalse($this->handle(threshold: 3)['everything']);

        $source->missed = true;

        $this->assertTrue($this->handle(threshold: 3)['everything']);
    }

    #[Test]
    public function it_starts_a_baseline_for_added_sources_and_forgets_removed_sources(): void
    {
        $this->addSource('one', new FakeSource(changes: ['products' => [1]]));
        $this->handle();
        $this->addSource('two', new FakeSource(changes: ['products' => [2]]));

        $this->assertEquals([1], $this->handle()['products']);
        $this->assertEquals([1, 2], $this->handle()['products']);

        config(['rapidez.cache-triggers-poc.sources' => ['two' => 'fake.two']]);

        $this->assertEquals(['everything' => false, 'products' => [2]], $this->handle());
    }

    #[Test]
    public function it_reports_exceptions_and_returns_everything(): void
    {
        Exceptions::fake();
        $source = $this->addSource('one', new FakeSource(state: 1, changes: fn (int $from, int $to): array => ['products' => range($from + 1, $to)]));
        $this->handle();

        $source->state = 2;
        $source->changedException = new RuntimeException('Changes failed');

        $this->assertEquals(['everything' => true], $this->handle());

        $source->changedException = null;
        $source->state = 3;
        $source->stateException = new RuntimeException('State failed');

        $this->assertEquals(['everything' => true], $this->handle());

        $source->stateException = null;
        $source->state = 4;

        $this->assertEquals(['everything' => false, 'products' => [3, 4]], $this->handle());
        Exceptions::assertReportedCount(2);
    }
}
