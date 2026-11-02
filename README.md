# Rapidez rapidez-cache-triggers-poc

This is a proof of concept for using Magento's own indexer changelog (`*_cl`) tables to know when product, category and attribute data has changed (mainly for cache clearing purposes). It does not add any triggers or tables to the database.

## Installation

Not actually on packagist. Add this repo to your composer repositories and then do:

```
composer require rapidez/rapidez-cache-triggers-poc
```

### Magento indexers

This package reads the changelog (`*_cl`) tables of the following Magento indexers. These tables only exist when the indexer is set to "Update by Schedule", indexers that aren't are skipped (and their changes won't be detected).

Magento fills these changelogs with database triggers on the tables each indexer subscribes to (see the `mview.xml` files), so they contain the ids of every product or category with a changed row in those tables, also for direct database writes like the Akeneo connector's. We don't use the indexers themselves, only their changelogs.

| Indexer | Title in the admin | Used for |
|---|---|---|
| `catalog_product_attribute` | Product EAV | `int`, `decimal` and `varchar` attribute values, product links |
| `catalog_product_price` | Product Price | Prices, tier prices, catalog rule prices, `datetime` attribute values, websites |
| `cataloginventory_stock` | Stock | Stock items |
| `catalog_product_category` | Product Categories | Category assignments, status/visibility and websites (product and its categories) |
| `catalog_category_product` | Category Products | Categories themselves |
| `catalogsearch_fulltext` | Catalog Search | `text` attribute values and configurable/bundle children, no other indexer subscribes to those |

```
bin/magento indexer:set-mode schedule catalog_product_attribute catalog_product_price cataloginventory_stock catalog_product_category catalog_category_product catalogsearch_fulltext
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

`changelogs` contains the changelog models that are read. Each model extends `Rapidez\RapidezCacheTriggersPoc\Models\Changelog` and only defines its Magento view. The ids in these changelogs are product ids, except for `CategoryProductChangelog` (category ids). For `ProductCategoryChangelog` the categories of the changed products are returned as well.

## Usage

This package exposes an action which will tell you which products and categories have changed since the last time you called it:

```php
$changes = $this->getUpdatedItemsAction->execute();

if ($changes['flush']) {
    // Changes were missed, clear everything
}

$changes['products'];   // Collection of product ids, including parents of changed children
$changes['categories']; // Collection of category ids
```

The position in each changelog is stored in `.rapidez-cache-state` on the `local` storage disk, so it survives cache clears and deployments (as long as the storage directory is shared between releases). The first call (when there is no cursor yet) only creates a baseline and returns no changes.

Changes are returned as soon as they're in the changelog, without waiting for Magento's indexer to process them.

When an attribute option label or swatch changes, the products that use that option (and their parents) are returned as well.

`flush` is set when:
- An attribute definition, attribute label or attribute set changed (these affect almost every page).
- Changelog entries were reindexed and cleaned up by Magento (`indexer_clean_all_changelogs`, hourly) before they were read, or when a changelog was reset.

## Todo

This is a proof of concept; these are known things to address before using it in production:

- Only rebuild the attribute option hashes when the option tables changed (e.g. using `CHECKSUM TABLE`). Building them takes ~1.4s and ~80MB for 20.000 options on every call.
- Chunk the `whereIn` queries for parents and categories, very large changesets (e.g. a full Akeneo import) can exceed MySQL's placeholder limit.
- The state is saved before the changes are processed, if processing fails those changes are lost. Return the new state and only save it after processing succeeded.
- A missing state file starts a new baseline, it should probably trigger a flush when there was a state before (e.g. after a lost storage directory).
- Products that used a deleted attribute option are not returned.
- Attribute changes of any entity type (e.g. customer attributes) trigger a flush, this could be limited to product attributes.
- Changes aren't store specific.
- Categories that a product was removed from are not returned.
- MSI's `inventory` changelog (source item ids) isn't used, stock changes only come through `cataloginventory_stock`.
- Rapidez still reads the special price from `catalog_product_index_price` and `category_ids` from `catalog_category_product_index_store*`. Because changes are returned before Magento reindexes, a page can be cached again with the old special price or categories until the next change or full flush. Clearing these products again once Magento has processed the change (`mview_state.version_id`) would fix this.
- Category `varchar`/`text` attribute changes (name, description, URL key, image, meta fields) aren't detected, because `catalog_category_product` only subscribes to `catalog_category_entity` and `catalog_category_entity_int` (and `updated_at` changes are ignored by Magento's triggers). The `catalog_category_flat` view subscribes to all category EAV tables, but Magento removes the flat indexers when the flat catalog is disabled, so it can't be used. These could be detected with a snapshot, like attribute options.
- Changes that are written, reindexed and cleaned up by Magento between two runs are lost and cause a flush. Running the changelog cleanup less often (or taking our position into account) would prevent this.

## License

GNU General Public License v3. Please see [License File](LICENSE) for more information.
