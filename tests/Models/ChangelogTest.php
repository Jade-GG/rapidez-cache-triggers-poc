<?php

declare(strict_types=1);

namespace Rapidez\RapidezCacheTriggersPoc\Tests\Models;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Rapidez\RapidezCacheTriggersPoc\Exceptions\SourceUnavailableException;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\ProductCategoryChangelog;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\ProductPriceChangelog;
use Rapidez\RapidezCacheTriggersPoc\Tests\TestCase;

final class ChangelogTest extends TestCase
{
    #[Test]
    public function it_returns_the_latest_version_only_when_subscribed(): void
    {
        $changelog = new ProductPriceChangelog;

        $this->assertThrows(fn () => $changelog->getState(null), SourceUnavailableException::class);

        $this->subscribe('catalog_product_price', 'disabled');

        $this->assertThrows(fn () => $changelog->getState(null), SourceUnavailableException::class);

        DB::table('mview_state')->update(['mode' => 'enabled']);
        $this->addChangelogEntries('catalog_product_price', [10, 11, 12]);

        $this->assertSame(3, $changelog->getState(null));
    }

    #[Test]
    public function it_detects_missed_changes(): void
    {
        $changelog = new ProductPriceChangelog;
        $this->addChangelogEntries('catalog_product_price', [10, 11, 12, 13, 14]);
        DB::table('catalog_product_price_cl')->where('version_id', 4)->delete();

        $this->assertFalse($changelog->hasMissedChanges(3, 3), 'Nothing changed');
        $this->assertFalse($changelog->hasMissedChanges(3, 5), 'The previous version still exists, gaps are ignored');
        $this->assertFalse($changelog->hasMissedChanges(0, 5), 'The changelog was empty');
        $this->assertTrue($changelog->hasMissedChanges(5, 3), 'The changelog was reset');

        DB::table('catalog_product_price_cl')->where('version_id', '<', 4)->delete();

        $this->assertTrue($changelog->hasMissedChanges(3, 5), 'Magento cleaned up the previous version');
    }

    #[Test]
    public function it_returns_the_changed_products_and_their_categories(): void
    {
        $this->addChangelogEntries('catalog_product_category', [9, 10, 10, 11, 12]);
        DB::table('catalog_category_product')->insert([
            ['category_id' => 3, 'product_id' => 10],
            ['category_id' => 4, 'product_id' => 10],
            ['category_id' => 4, 'product_id' => 11],
            ['category_id' => 5, 'product_id' => 12],
        ]);

        $changes = (new ProductCategoryChangelog)->getChanged(1, 4);

        $this->assertEqualsCanonicalizing([10, 11], $changes['products']->all());
        $this->assertEqualsCanonicalizing([3, 4], $changes['categories']->all());
        $this->assertEquals(['products'], (new ProductPriceChangelog)->getChanged(0, 0)->keys()->all());
    }
}
