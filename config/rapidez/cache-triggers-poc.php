<?php

return [
    'views' => [
        'products' => [
            'catalogrule_product',
            'catalog_product_price',
            'catalog_product_attribute',
            'catalog_product_category',
            'cataloginventory_stock',
            'catalogsearch_fulltext',
        ],

        'categories' => [
            'catalog_category_product',
        ],

        'product_categories' => [
            'catalog_product_category',
        ],
    ],

    'attribute_tables' => [
        'eav_attribute',
        'catalog_eav_attribute',
        'eav_attribute_label',
        'eav_attribute_option',
        'eav_attribute_option_value',
        'eav_attribute_option_swatch',
        'eav_entity_attribute',
    ],
];
