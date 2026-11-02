<?php

namespace Rapidez\RapidezCacheTriggersPoc\Actions;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GetUpdatedItemsAction
{
    /**
     * @param array<string, mixed>|null $cursor
     * @return array{products: Collection<int, int>, categories: Collection<int, int>, attributes: Collection<int, int>, flush: bool, cursor: array<string, mixed>}
     */
    public function getChanges(?array $cursor = null): array
    {
        $flush = false;
        $versions = [];
        $changes = [];

        foreach ($this->views() as $view) {
            $state = DB::table('mview_state')
                ->where('view_id', $view)
                ->where('mode', 'enabled')
                ->value('version_id');

            if ($state === null || ! Schema::hasTable("{$view}_cl")) {
                continue;
            }

            $state = (int) $state;
            $from = $cursor['views'][$view] ?? null;
            $versions[$view] = $state;

            if ($from === null) {
                continue;
            }

            if ($from > $state || $this->hasGap($view, $from, $state)) {
                $flush = true;

                continue;
            }

            $changes[$view] = DB::table("{$view}_cl")
                ->where('version_id', '>', $from)
                ->where('version_id', '<=', $state)
                ->distinct()
                ->pluck('entity_id');
        }

        $products = $this->changesFor('products', $changes);
        $products = $products->merge($this->lookup('catalog_product_relation', 'child_id', 'parent_id', $products));

        $categories = $this->changesFor('categories', $changes)
            ->merge($this->lookup('catalog_category_product', 'product_id', 'category_id', $this->changesFor('product_categories', $changes)));

        [$attributes, $attributeSnapshot] = $this->attributeChanges($cursor['attributes'] ?? null);

        return [
            'products' => $products->map(fn ($id) => (int) $id)->unique()->values(),
            'categories' => $categories->map(fn ($id) => (int) $id)->unique()->values(),
            'attributes' => $attributes,
            'flush' => $flush,
            'cursor' => [
                'views' => $versions,
                'attributes' => $attributeSnapshot,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function views(): array
    {
        return collect(config('rapidez.cache-triggers-poc.views'))->flatten()->unique()->values()->all();
    }

    protected function hasGap(string $view, int $from, int $to): bool
    {
        if ($from === $to) {
            return false;
        }

        $first = DB::table("{$view}_cl")->where('version_id', '>', $from)->min('version_id');

        return $first === null || $first > $from + 1;
    }

    /**
     * @param array<string, Collection<int, int>> $changes
     * @return Collection<int, int>
     */
    protected function changesFor(string $type, array $changes): Collection
    {
        return collect(config("rapidez.cache-triggers-poc.views.{$type}"))
            ->flatMap(fn (string $view) => $changes[$view] ?? [])
            ->unique()
            ->values();
    }

    /**
     * @param Collection<int, int> $ids
     * @return Collection<int, int>
     */
    protected function lookup(string $table, string $column, string $result, Collection $ids): Collection
    {
        return $ids
            ->chunk(1000)
            ->flatMap(fn (Collection $chunk) => DB::table($table)->whereIn($column, $chunk->values())->distinct()->pluck($result));
    }

    /**
     * @param array{checksums: array<string, mixed>, hashes: array<int, string>}|null $previous
     * @return array{0: Collection<int, int>, 1: array{checksums: array<string, mixed>, hashes: array<int, string>}}
     */
    protected function attributeChanges(?array $previous): array
    {
        $checksums = $this->attributeChecksums();

        if ($previous && $previous['checksums'] === $checksums) {
            return [collect(), $previous];
        }

        $hashes = $this->attributeHashes();
        $snapshot = ['checksums' => $checksums, 'hashes' => $hashes];

        if (! $previous) {
            return [collect(), $snapshot];
        }

        $changed = collect(array_keys($hashes + $previous['hashes']))
            ->filter(fn (int $id) => ($hashes[$id] ?? null) !== ($previous['hashes'][$id] ?? null))
            ->values();

        return [$changed, $snapshot];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributeChecksums(): array
    {
        $tables = implode(', ', config('rapidez.cache-triggers-poc.attribute_tables'));

        return collect(DB::select("CHECKSUM TABLE {$tables}"))
            ->mapWithKeys(fn (object $row) => [$row->Table => $row->Checksum])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function attributeHashes(): array
    {
        $rows = [];

        foreach (config('rapidez.cache-triggers-poc.attribute_tables') as $table) {
            $primaryKey = $this->primaryKey($table);

            foreach ($this->attributeQuery($table)->cursor() as $row) {
                $row = (array) $row;
                $attributeId = $row['attribute_id'];
                unset($row[$primaryKey]);
                $rows[$attributeId][] = json_encode($row);
            }
        }

        return array_map(function (array $rows) {
            sort($rows);

            return md5(implode("\n", $rows));
        }, $rows);
    }

    protected function attributeQuery(string $table): Builder
    {
        if (! Schema::hasColumn($table, 'attribute_id')) {
            return DB::table($table)
                ->join('eav_attribute_option', 'eav_attribute_option.option_id', '=', "{$table}.option_id")
                ->select("{$table}.*", 'eav_attribute_option.attribute_id');
        }

        return DB::table($table);
    }

    protected function primaryKey(string $table): ?string
    {
        return collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'][0] ?? null;
    }
}
