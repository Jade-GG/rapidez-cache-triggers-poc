# Rapidez rapidez-cache-triggers-poc

This is a proof of concept for using Magento's own indexer changelog (`*_cl`) tables to know when product, category and attribute data has changed (mainly for cache clearing purposes). It does not add any triggers or tables to the database.

## Installation

Not actually on packagist. Add this repo to your composer repositories and then do:

```
composer require rapidez/rapidez-cache-triggers-poc
```

### Magento indexers

Products and categories with a changed `updated_at` are always returned. Magento updates this on every save, and the Akeneo connector on every import, but not for changes that bypass the product or category itself (mass attribute updates, the price APIs, stock, category assignments, ...). For those, this package reads the changelog (`*_cl`) tables of the following Magento indexers. These tables only exist when the indexer is set to "Update by Schedule", indexers that aren't are skipped (and their changes won't be detected).

Magento fills these changelogs with database triggers on the tables each indexer subscribes to (see the `mview.xml` files), so they contain the ids of every product or category with a changed row in those tables, also for direct database writes like the Akeneo connector's. We don't use the indexers themselves, only their changelogs.

| Indexer                     | Title in the admin | Used for                                                                          |
| --------------------------- | ------------------ | --------------------------------------------------------------------------------- |
| `catalog_product_attribute` | Product EAV        | `int`, `decimal` and `varchar` attribute values, product links                    |
| `catalog_product_price`     | Product Price      | Prices, tier prices, catalog rule prices, `datetime` attribute values, websites   |
| `cataloginventory_stock`    | Stock              | Stock items                                                                       |
| `catalog_product_category`  | Product Categories | Category assignments, status/visibility and websites (product and its categories) |

```
bin/magento indexer:set-mode schedule catalog_product_attribute catalog_product_price cataloginventory_stock catalog_product_category
```

Impact of "Update by Schedule" on Magento:

- Saving a product or category no longer reindexes immediately, the Magento cron (`indexer_update_all_views`, every minute by default) does it instead. Admin saves and imports get faster, but index tables (e.g. the price index with special and catalog rule prices) are updated a little later. The Magento cron must be running.
- Magento adds database triggers to the catalog tables to fill the changelogs. This is Magento's own, recommended mechanism, and is what makes changes from direct database writes (like the Akeneo connector) get picked up.
- The changelogs are cleaned up by Magento (`indexer_clean_all_changelogs`, hourly by default).

## Configuration

You can publish the config with:

```
php artisan vendor:publish --tag=rapidez-cache-triggers-poc-config
```

`sources` contains the classes that detect changes. Each source implements `Rapidez\RapidezCacheTriggersPoc\Sources\Source`:

- `getState($previous)` returns the current position (e.g. a changelog version, a time or a checksum). It is stored per source and passed back as `$from` and `$to`. When it returns `null`, the source is skipped (e.g. a changelog of an indexer that isn't set to "Update by Schedule").
- `hasMissedChanges($from, $to)` returns `true` when the changes can't be determined, `everything` is then set.
- `getChanged($from, $to)` returns the changed ids per type, e.g. `['products' => ..., 'categories' => ...]`.

| Source | Returns |
|---|---|
| `AttributeSource` | `everything` when an attribute definition, label or set changed |
| `AttributeOptionSource` | `products` that use a changed option, `everything` when an option was deleted |
| `ProductUpdatedAtSource`, `CategoryUpdatedAtSource` | `products` / `categories` with a changed `updated_at` |
| `*Changelog` | `products` from the Magento changelogs above, `ProductCategoryChangelog` also returns their `categories` |

Changelogs extend `Rapidez\RapidezCacheTriggersPoc\Models\Changelog` and only define their view and `getChanged()`, `getChangedIds()` gives you the ids in the changelog. When these are other ids (e.g. `source_item_id` for MSI's `inventory` view), map them to product or category ids. For `updated_at` of other models, extend `Rapidez\RapidezCacheTriggersPoc\Sources\UpdatedAtSource`.

Other packages can add their own sources (and types) in the `boot()` method of their service provider:

```php
if (class_exists(Source::class)) {
    config(['rapidez.cache-triggers-poc.sources.inventory' => InventoryChangelog::class]);
}
```

After the threshold check, each type is passed through the `cache-triggers-poc.changes.{type}` Eventy filter. This package uses it to add the parents of changed products and categories, other packages can use it to add related ids:

```php
Eventy::addFilter('cache-triggers-poc.changes.products', fn (Collection $products) => $products->merge(...));
```

`threshold` is the default maximum number of changed ids of all types together (before the Eventy filters add parents). Above this number, `everything` is set. Keep this below the purge limits of your CDN (e.g. Cloudflare). You can give each consumer its own threshold with the `threshold` argument of `handle()`.

## Usage

This package exposes an action which will tell you which products and categories have changed since the last time you called it:

```php
$changes = $this->getUpdatedItemsAction->handle('cloudflare', threshold: 200);

if ($changes['everything']) {
    // Clear everything, no other keys are returned
}

$changes['products'];   // Collection of product ids, including parents of changed children
$changes['categories']; // Collection of category ids, including their parent categories
// Plus any other types that your sources return
```

The state of each source is stored in `.rapidez-changelog-{your tag here}` on the `local` storage disk, so it survives cache clears and deployments (as long as the storage directory is shared between releases). The first call (when there is no state yet) creates a baseline and returns `everything`.

Changes are returned as soon as they're in the changelog, without waiting for Magento's indexer to process them.

When an attribute option label or swatch changes, the products that use that option (and their parents) are returned as well.

`everything` is set when (no other keys are then returned):

- An attribute definition, attribute label or attribute set changed (these affect almost every page).
- Changelog entries were reindexed and cleaned up by Magento (`indexer_clean_all_changelogs`, hourly) before they were read, or when a changelog was reset.
- An attribute option was deleted.
- More ids changed than the threshold.
- There is no state yet for this tag.

## Todo

This is a proof of concept; these are known things to address before using it in production:

- The state is saved before the changes are processed, if processing fails those changes are lost. Return the new state and only save it after processing succeeded.
- Attribute changes of any entity type (e.g. customer attributes) trigger a flush, this could be limited to product attributes.
- Changes aren't store specific.
- Categories that a product was removed from are not returned.
- `rapidez/msi` reads stock from the `inventory_stock_*` index tables. It should add a changelog for the `inventory` view (source item ids), which is only correct once changes can be returned after Magento reindexes them (see below). Salability changes from reservations (orders) aren't in any changelog.
- Rapidez still reads the special price from `catalog_product_index_price` and `category_ids` from `catalog_category_product_index_store*`. Because changes are returned before Magento reindexes, a page can be cached again with the old special price or categories until the next change or full flush. Clearing these products again once Magento has processed the change (`mview_state.version_id`) would fix this.
- Product `text` attribute changes that don't update `updated_at` (e.g. the mass attribute update) aren't detected, no aptly named indexer subscribes to `catalog_product_entity_text` (only `catalogsearch_fulltext` and `catalogrule_product` do).
- A child that's removed from a configurable or bundle by a direct database write, without updating the parent, doesn't return the parent anymore.
- The Akeneo connector updates `updated_at` before it writes the new values. If this runs during an import, the product can be cleared before its new `text` values are written (other values are caught by the changelogs once they're written), and get cached with the old values.
- Changes that are written, reindexed and cleaned up by Magento between two runs are lost and cause a flush. Running the changelog cleanup less often (or taking our position into account) would prevent this.

## License

GNU General Public License v3. Please see [License File](LICENSE) for more information.
