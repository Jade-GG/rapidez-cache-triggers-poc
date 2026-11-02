<?php

namespace Rapidez\RapidezCacheTriggersPoc\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rapidez\RapidezCacheTriggersPoc\Models\AttributeOption;
use Rapidez\RapidezCacheTriggersPoc\Models\CategoryProductLink;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelog;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\CategoryProductChangelog;
use Rapidez\RapidezCacheTriggersPoc\Models\Changelogs\ProductCategoryChangelog;

class GetUpdatedItemsAction
{
    public const STATE_PATH = '/.rapidez-cache-state';

    /**
     * @return array{products: Collection<int, int>, categories: Collection<int, int>, flush: bool}
     */
    public function execute(): array
    {
        $previous = json_decode(Storage::disk('local')->get(static::STATE_PATH) ?? '', true);
        $state = [
            'versions' => $this->getVersions(),
            'attributes' => $this->getChecksum(['eav_attribute', 'catalog_eav_attribute', 'eav_attribute_label', 'eav_entity_attribute']),
            'options' => AttributeOption::getHashes(),
        ];
        Storage::disk('local')->put(static::STATE_PATH, json_encode($state));

        $products = collect();
        $categories = collect();
        $flush = false;

        if (! $previous) {
            return [
                'products' => $products,
                'categories' => $categories,
                'flush' => $flush,
            ];
        }

        foreach (config('rapidez.cache-triggers-poc.changelogs') as $class) {
            $from = $previous['versions'][$class] ?? null;
            $to = $state['versions'][$class] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            /** @var Changelog $changelog */
            $changelog = new $class;

            if ($changelog->hasMissedChanges($from, $to)) {
                $flush = true;

                continue;
            }

            $ids = $changelog->getChangedIds($from, $to);

            if ($changelog instanceof CategoryProductChangelog) {
                $categories = $categories->merge($ids);
            } else {
                $products = $products->merge($ids);
            }

            if ($changelog instanceof ProductCategoryChangelog) {
                $categories = $categories->merge($this->getCategories($ids));
            }
        }

        $products = $products->merge(AttributeOption::getProducts(array_keys(array_diff_assoc($state['options'], $previous['options']))));
        $products = $products->merge($this->getParents($products));

        return [
            'products' => $products->unique()->values(),
            'categories' => $categories->unique()->values(),
            'flush' => $flush || $state['attributes'] !== $previous['attributes'],
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function getVersions(): array
    {
        return collect(config('rapidez.cache-triggers-poc.changelogs'))
            ->mapWithKeys(fn (string $class) => [$class => (new $class)->getVersion()])
            ->filter(fn (?int $version) => $version !== null)
            ->all();
    }

    protected function getChecksum(array $tables): string
    {
        return collect(DB::select('CHECKSUM TABLE '.implode(', ', $tables)))->pluck('Checksum')->implode(',');
    }

    /**
     * @param Collection<int, int> $products
     * @return Collection<int, int>
     */
    protected function getParents(Collection $products): Collection
    {
        return config('rapidez.models.product')::withoutGlobalScopes()
            ->whereHas('children', fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('child_id', $products))
            ->pluck('entity_id');
    }

    /**
     * @param Collection<int, int> $products
     * @return Collection<int, int>
     */
    protected function getCategories(Collection $products): Collection
    {
        return CategoryProductLink::whereIn('product_id', $products)->pluck('category_id');
    }
}
