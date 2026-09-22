<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models;

use Illuminate\Database\Eloquent\Model;

class ProductChangelog extends Model
{
    public $timestamps = false;

    protected $table = 'rapidez_product_changelog';

    protected $primaryKey = 'version_id';

    protected $guarded = [];
}
