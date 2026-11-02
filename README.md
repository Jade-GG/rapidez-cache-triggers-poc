# Rapidez rapidez-cache-triggers-poc

This is a proof of concept for using Magento's own indexer changelog (`*_cl`) tables to know when product, category and attribute data has changed (mainly for cache clearing purposes). It does not add any triggers or tables to the database.

## Installation

Not actually on packagist. Add this repo to your composer repositories and then do:

```
composer require rapidez/rapidez-cache-triggers-poc
```

The indexers for the configured views need to be set to "Update by Schedule", and the Magento cron needs to be running:

```
bin/magento indexer:set-mode schedule
```

Views that aren't on schedule are skipped.

## Configuration

You can publish the config with:
```
php artisan vendor:publish --tag=rapidez-cache-triggers-poc-config
```

- `views.products`: changelogs whose entity ids are product ids.
- `views.categories`: changelogs whose entity ids are category ids.
- `views.product_categories`: changelogs with product ids whose (current) categories should be marked as changed as well.
- `attribute_tables`: tables that are compared to detect attribute definition, label, option and attribute set changes.

## Usage

This package exposes an action which will tell you which products, categories or attributes have changed since the last time you called it. You keep track of the returned cursor yourself and pass it to the next call:

```php
$changes = $this->getUpdatedItemsAction->getChanges(Cache::get('rapidez-cache-cursor'));

Cache::forever('rapidez-cache-cursor', $changes['cursor']);

if ($changes['flush']) {
    // Changes were missed, clear everything
}

$changes['products'];   // Collection of product ids, including parents of changed children
$changes['categories']; // Collection of category ids
$changes['attributes']; // Collection of attribute ids
```

The first call (without a cursor) only creates a baseline and returns no changes.

Only changes that Magento has already reindexed are returned, so data in the index tables is up to date when you clear your cache.

`flush` is set when changelog entries were cleaned up by Magento (`indexer_clean_all_changelogs`, hourly) before they were read, or when a changelog was reset. Call `getChanges()` more often than the changelog cleanup runs.

Changes aren't store specific, and categories that a product was removed from are not returned.

## License

GNU General Public License v3. Please see [License File](LICENSE) for more information.
