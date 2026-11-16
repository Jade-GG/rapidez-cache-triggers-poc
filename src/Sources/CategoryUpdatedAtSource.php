<?php

namespace Rapidez\RapidezCacheTriggersPoc\Sources;

use Illuminate\Database\Eloquent\Builder;

class CategoryUpdatedAtSource extends UpdatedAtSource
{
    protected string $type = 'categories';

    protected function query(): Builder
    {
        return config('rapidez.models.category')::withoutGlobalScopes();
    }
}
