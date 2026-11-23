# Rapidez rapidez-cache-triggers-poc

This is a proof of concept for using Magento's own indexer changelog (`*_cl`) tables to know when product, category and attribute data has changed (mainly for cache clearing purposes). It does not add any triggers or tables to the database.

## Installation

```
composer require rapidez/rapidez-cache-triggers-poc
```

### Magento indexers

You will need to set the following Magento indexers to Update by Schedule:

```
bin/magento indexer:set-mode schedule catalog_product_attribute catalog_product_price cataloginventory_stock catalog_product_category catalogsearch_fulltext
```

## Configuration

You can publish the config with:

```
php artisan vendor:publish --tag=rapidez-cache-triggers-poc-config
```

You can add extra sources here, as well as changing the default `everything` threshold.

## Usage

This package contains an action which returns a list of all changes since you last called it:

```php
$changes = $this->getUpdatedItemsAction->handle();
```

This will return an array containing all changes or simply an array with `everything` set to true if the changes exceed the threshold, or the exact changes cannot be determined. Note that the array will **not** contain anything else when this is set.

The latest state will get saved as a file in the storage folder to protect it from cache clears.

### Tags and Thresholds

You can also pass a tag and custom threshold:

```php
$changes = $this->getUpdatedItemsAction->handle('cloudflare', 250);
```

Using a custom tag allows you to keep track of separate updated actions; these get saved individually per tag. The default tag is `default`.

## Extending

### Sources

If you want to use more sources to clear the cache from, you can create a new model that implements the `Source` interface.

Look at `UpdatedAtSource` for a simple example of this.

- `getState($previous)` this is used to determine whether or not the state of this cache has changed, and thus should be checked.
- `hasMissedChanges($from, $to)` should return `true` when something has gone wrong and changes have been missed. This will trigger an `everything` clear.
- `getChanged($from, $to)` returns an array of the changed ids per type. These could be any type you want, for example `['products' => ..., 'wishlists' => ...]`.

For changelog tables, you can extend the `Changelog` class. This class provides all of the relevant functionality for these tables. `ProductCategoryChangelog` is a good example of this.

These sources can then be added to the config to make them functional.

### Transformations

You may want to transform the resulting data after all of the sources have returned. For example, within this package we add the parent products of any changed child products using the `AddProductParents` transform.

You can add more filters like so:

```php
Eventy::addFilter('cache-triggers-poc.changes.products', fn (Collection $products) => $products->merge(...));
```

## License

GNU General Public License v3. Please see [License File](LICENSE) for more information.
