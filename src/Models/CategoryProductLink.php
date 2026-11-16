<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models;

use Rapidez\Core\Models\Model;

class CategoryProductLink extends Model
{
    public $timestamps = false;

    protected $table = 'catalog_category_product';

    protected $primaryKey = 'entity_id';
}
