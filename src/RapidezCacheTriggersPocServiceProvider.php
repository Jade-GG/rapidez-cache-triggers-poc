<?php

namespace Rapidez\RapidezCacheTriggersPoc;

use Illuminate\Support\ServiceProvider;
use Rapidez\RapidezCacheTriggersPoc\Transforms\AddCategoryParents;
use Rapidez\RapidezCacheTriggersPoc\Transforms\AddProductParents;
use TorMorten\Eventy\Facades\Eventy;

class RapidezCacheTriggersPocServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rapidez/cache-triggers-poc.php', 'rapidez.cache-triggers-poc');
    }

    public function boot()
    {
        $this->bootPublishables();

        Eventy::addFilter('cache-triggers-poc.changes.products', AddProductParents::class);
        Eventy::addFilter('cache-triggers-poc.changes.categories', AddCategoryParents::class);
    }

    public function bootPublishables(): static
    {
        $this->publishes([
            __DIR__.'/../config/rapidez/cache-triggers-poc.php' => config_path('rapidez/cache-triggers-poc.php'),
        ], 'rapidez-cache-triggers-poc-config');

        return $this;
    }
}
