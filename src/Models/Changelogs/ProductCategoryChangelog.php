<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models\Changelogs;

use Illuminate\Support\Collection;
use Rapidez\RapidezCacheTriggersPoc\Models\CategoryProductLink;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelog;

class ProductCategoryChangelog extends Changelog
{
    protected string $view = 'catalog_product_category';

    /**
     * @return Collection<string, Collection<int, int>>
     */
    public function getChanged(mixed $from, mixed $to): Collection
    {
        $products = $this->getChangedIds($from, $to);

        return collect([
            'products' => $products,
            'categories' => CategoryProductLink::whereIn('product_id', $products)->distinct()->pluck('category_id'),
        ]);
    }
}
