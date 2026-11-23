<?php

return [
    'sources' => [
        'attributes' => Rapidez\RapidezCacheTriggersPoc\Sources\AttributeSource::class,
        'attribute_options' => Rapidez\RapidezCacheTriggersPoc\Sources\AttributeOptionSource::class,
        'product_updated_at' => Rapidez\RapidezCacheTriggersPoc\Sources\ProductUpdatedAtSource::class,
        'category_updated_at' => Rapidez\RapidezCacheTriggersPoc\Sources\CategoryUpdatedAtSource::class,
        'catalog_product_attribute' => Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\ProductAttributeChangelog::class,
        'catalog_product_price' => Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\ProductPriceChangelog::class,
        'cataloginventory_stock' => Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\StockChangelog::class,
        'catalog_product_category' => Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\ProductCategoryChangelog::class,
    ],

    'threshold' => 200,
];
