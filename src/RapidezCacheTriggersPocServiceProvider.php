<?php

namespace Rapidez\RapidezCacheTriggersPoc;

use Illuminate\Support\ServiceProvider;

class RapidezCacheTriggersPocServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rapidez/cache-triggers-poc.php', 'rapidez.cache-triggers-poc');
    }

    public function boot()
    {
        $this->bootPublishables();
    }

    public function bootPublishables(): static
    {
        $this->publishes([
            __DIR__.'/../config/rapidez/cache-triggers-poc.php' => config_path('rapidez/cache-triggers-poc.php'),
        ], 'rapidez-cache-triggers-poc-config');

        return $this;
    }
}
