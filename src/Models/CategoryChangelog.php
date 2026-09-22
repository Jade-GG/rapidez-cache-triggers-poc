<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryChangelog extends Model
{
    public $timestamps = false;

    protected $table = 'rapidez_category_changelog';

    protected $primaryKey = 'version_id';

    protected $guarded = [];
}
