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

This package exposes an action which will tell you which products or categories have changed since a certain date.

For example:

```php
$this->getUpdatedItemsAction
    ->getSince(now()->subMinutes(5));
```

You can also filter by trigger, using either the `onlyOn()` or `exceptOn()` chained functions:

```php
$this->getUpdatedItemsAction
    ->onlyOn(['eav_attribute_label_ai','eav_attribute_label_au','eav_attribute_label_ad'])
    ->getSince(now()->subMinutes(5));
```

## License

GNU General Public License v3. Please see [License File](LICENSE) for more information.
