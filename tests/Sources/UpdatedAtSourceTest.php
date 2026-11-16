<?php

declare(strict_types=1);

namespace Rapidez\RapidezCacheTriggersPoc\Tests\Sources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Rapidez\RapidezCacheTriggersPoc\Sources\CategoryUpdatedAtSource;
use Rapidez\RapidezCacheTriggersPoc\Sources\ProductUpdatedAtSource;
use Rapidez\RapidezCacheTriggersPoc\Tests\TestCase;

final class UpdatedAtSourceTest extends TestCase
{
    #[Test]
    public function it_returns_the_entities_updated_since_the_previous_state(): void
    {
        Carbon::setTestNow('2026-11-16 12:01:00');
        DB::table('catalog_product_entity')->insert([
            ['entity_id' => 1, 'updated_at' => '2026-11-16 11:59:59'],
            ['entity_id' => 2, 'updated_at' => '2026-11-16 12:00:00'],
        ]);
        DB::table('catalog_category_entity')->insert([
            ['entity_id' => 1, 'path' => '1', 'updated_at' => '2026-11-16 12:00:30'],
        ]);
        $source = new ProductUpdatedAtSource;

        $this->assertSame('2026-11-16 12:01:00', $source->getState(null));
        $this->assertFalse($source->hasMissedChanges('2026-11-16 12:00:00', '2026-11-16 12:01:00'));
        $this->assertEquals(['products' => [2]], $source->getChanged('2026-11-16 12:00:00', '2026-11-16 12:01:00')->toArray());
        $this->assertEquals(['categories' => [1]], (new CategoryUpdatedAtSource)->getChanged('2026-11-16 12:00:00', '2026-11-16 12:01:00')->toArray());
    }
}
