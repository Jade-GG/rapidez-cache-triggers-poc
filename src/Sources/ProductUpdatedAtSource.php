<?php

namespace Rapidez\RapidezCacheTriggersPoc\Sources;

use Illuminate\Database\Eloquent\Builder;

class ProductUpdatedAtSource extends UpdatedAtSource
{
    protected string $type = 'products';

    protected function query(): Builder
    {
        return config('rapidez.models.product')::withoutGlobalScopes();
    }
}
