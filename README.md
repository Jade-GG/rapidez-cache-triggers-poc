# Rapidez rapidez-cache-triggers-poc

This is a proof of concept for using triggers to know when product and category data has changed (mainly for cache clearing purposes)

## Installation

Not actually on packagist. Add this repo to your composer repositories and then do:

```
composer require rapidez/rapidez-cache-triggers-poc
```

Make sure to run `php artisan migrate` after installation to set up the triggers and changelog database tables.

## Configuration

You can publish the config with:
```
php artisan vendor:publish --tag=rapidez-cache-triggers-poc-config
```

## Usage

This package exposes an action which will tell you which products, categories or attributes have changed since a certain date. This can be in a given store, or across the board.

For example:

```php
$this->getUpdatedItemsAction
    ->store(2)
    ->getSince(now()->subMinutes(5));
```

You can also filter by trigger, using either the `onlyOn()` or `exceptOn()` chained functions:

```php
$this->getUpdatedItemsAction
    ->onlyOn(['catalog_category_product_ai','catalog_category_product_au','catalog_category_product_ad'])
    ->store(2)
    ->getSince(now()->subMinutes(5));
```

## License

GNU General Public License v3. Please see [License File](LICENSE) for more information.
