<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeChangelog extends Model
{
    public $timestamps = false;

    protected $table = 'rapidez_attribute_changelog';

    protected $primaryKey = 'version_id';

    protected $guarded = [];
}
