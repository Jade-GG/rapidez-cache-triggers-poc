<?php

declare(strict_types=1);

namespace Rapidez\RapidezCacheTriggersPoc\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Rapidez\Core\Models\Category;
use Rapidez\Core\Models\Product;
use Rapidez\RapidezCacheTriggersPoc\RapidezCacheTriggersPocServiceProvider;
use TorMorten\Eventy\EventServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EventServiceProvider::class,
            RapidezCacheTriggersPocServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('rapidez.models.product', Product::class);
        $app['config']->set('rapidez.models.category', Category::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()->createFunction('NOW', fn (): string => now()->toDateTimeString());

        Schema::create('mview_state', function (Blueprint $table): void {
            $table->string('view_id');
            $table->string('mode');
            $table->integer('version_id')->default(0);
        });

        foreach (['catalog_product_price_cl', 'catalog_product_category_cl'] as $changelog) {
            Schema::create($changelog, function (Blueprint $table): void {
                $table->increments('version_id');
                $table->integer('entity_id');
            });
        }

        Schema::create('catalog_product_entity', function (Blueprint $table): void {
            $table->increments('entity_id');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('catalog_product_relation', function (Blueprint $table): void {
            $table->integer('parent_id');
            $table->integer('child_id');
        });

        Schema::create('catalog_category_entity', function (Blueprint $table): void {
            $table->increments('entity_id');
            $table->string('path');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('catalog_category_product', function (Blueprint $table): void {
            $table->increments('entity_id');
            $table->integer('category_id');
            $table->integer('product_id');
        });

        Schema::create('eav_attribute_option', function (Blueprint $table): void {
            $table->increments('option_id');
            $table->integer('attribute_id');
            $table->integer('sort_order')->default(0);
        });
    }

    protected function subscribe(string $view, string $mode = 'enabled'): void
    {
        DB::table('mview_state')->insert(['view_id' => $view, 'mode' => $mode]);
    }

    /**
     * @param array<int, int> $entityIds
     */
    protected function addChangelogEntries(string $view, array $entityIds): void
    {
        DB::table($view . '_cl')->insert(array_map(fn (int $entityId): array => ['entity_id' => $entityId], $entityIds));
    }
}
