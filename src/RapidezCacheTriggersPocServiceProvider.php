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
        $this
            ->bootPublishables();
    }

    public function bootPublishables() : self
    {
        $this->publishes([
            __DIR__.'/../config/rapidez/cache-triggers-poc.php' => config_path('rapidez/cache-triggers-poc.php'),
        ], 'rapidez-cache-triggers-poc-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ]);

        return $this;
    }
}
