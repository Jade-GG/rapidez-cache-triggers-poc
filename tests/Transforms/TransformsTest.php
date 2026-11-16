<?php

declare(strict_types=1);

namespace Rapidez\RapidezCacheTriggersPoc\Tests\Transforms;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Rapidez\RapidezCacheTriggersPoc\Tests\TestCase;
use TorMorten\Eventy\Facades\Eventy;

final class TransformsTest extends TestCase
{
    #[Test]
    public function it_adds_the_parents_of_changed_products(): void
    {
        DB::table('catalog_product_entity')->insert([['entity_id' => 1], ['entity_id' => 2], ['entity_id' => 3], ['entity_id' => 4]]);
        DB::table('catalog_product_relation')->insert([['parent_id' => 1, 'child_id' => 2], ['parent_id' => 3, 'child_id' => 4]]);

        $this->assertEqualsCanonicalizing([1, 2], Eventy::filter('cache-triggers-poc.changes.products', collect([2]))->all());
    }

    #[Test]
    public function it_adds_the_parent_categories_without_the_root_categories(): void
    {
        DB::table('catalog_category_entity')->insert([
            ['entity_id' => 3, 'path' => '1/2/3'],
            ['entity_id' => 4, 'path' => '1/2/3/4'],
        ]);

        $this->assertEqualsCanonicalizing([3, 4, 9], Eventy::filter('cache-triggers-poc.changes.categories', collect([4, 9]))->unique()->all());
    }
}
