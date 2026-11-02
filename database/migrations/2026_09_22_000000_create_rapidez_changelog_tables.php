<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $productEavTables = [
        'catalog_product_entity_int',
        'catalog_product_entity_decimal',
        'catalog_product_entity_varchar',
        'catalog_product_entity_text',
        'catalog_product_entity_datetime',
    ];

    protected array $events = [
        'ai' => 'AFTER INSERT',
        'au' => 'AFTER UPDATE',
        'ad' => 'AFTER DELETE',
        'bd' => 'BEFORE DELETE',
    ];

    public function up(): void
    {
        $this->createChangelogTables();
        $this->createProductTriggers();
        $this->createCategoryTriggers();
        $this->createJunctionTriggers();
        $this->createAttributeTriggers();
    }

    public function down(): void
    {
        DB::table('information_schema.TRIGGERS')
            ->whereRaw('TRIGGER_SCHEMA = DATABASE()')
            ->where('TRIGGER_NAME', 'like', 'rapidez\_changelog\_%')
            ->pluck('TRIGGER_NAME')
            ->each(fn (string $trigger) => DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`"));

        Schema::dropIfExists('rapidez_product_changelog');
        Schema::dropIfExists('rapidez_category_changelog');
        Schema::dropIfExists('rapidez_attribute_changelog');
    }

    protected function createChangelogTables(): void
    {
        foreach (['product', 'category', 'attribute'] as $type) {
            Schema::create("rapidez_{$type}_changelog", function (Blueprint $table) use ($type) {
                $table->id('version_id');
                $table->unsignedInteger('entity_id');
                $table->unsignedSmallInteger('store_id')->default(0);
                if ($type === 'attribute') {
                    $table->unsignedSmallInteger('attribute_set_id')->nullable();
                }
                $table->string('trigger');
                $table->dateTime('created_at')->useCurrent();
                $table->index('entity_id');
                $table->index('store_id');
                $table->index('created_at');
            });
        }
    }

    protected function createProductTriggers(): void
    {
        $this->createTrigger('catalog_product_entity', 'ai', $this->log('product', ['entity_id' => 'NEW.entity_id']));
        $this->createTrigger('catalog_product_entity', 'au', $this->log('product', ['entity_id' => 'NEW.entity_id']));
        $this->createTrigger('catalog_product_entity', 'bd', implode("\n", [
            $this->log('product', ['entity_id' => 'OLD.entity_id']),
            $this->log('product', ['entity_id' => 'parent_id'], 'catalog_product_relation WHERE child_id = OLD.entity_id'),
            $this->log('category', ['entity_id' => 'category_id'], 'catalog_category_product WHERE product_id = OLD.entity_id'),
        ]));

        $statusVisibility = $this->statusVisibilityAttributeIds();

        foreach ($this->productEavTables as $table) {
            foreach (['ai' => 'NEW', 'au' => 'NEW', 'ad' => 'OLD'] as $event => $row) {
                $body = $this->log('product', ['entity_id' => "{$row}.entity_id", 'store_id' => "{$row}.store_id"]);

                if ($table === 'catalog_product_entity_int' && $statusVisibility) {
                    $body .= "\nIF {$row}.attribute_id IN ({$statusVisibility}) THEN "
                        . $this->log('category', ['entity_id' => 'category_id', 'store_id' => "{$row}.store_id"], "catalog_category_product WHERE product_id = {$row}.entity_id")
                        . ' END IF;';
                }

                if ($event === 'au') {
                    $body = $this->onlyIfChanged(['value'], $body);
                }

                $this->createTrigger($table, $event, $body);
            }
        }

        foreach (['ai' => 'NEW', 'au' => 'NEW', 'ad' => 'OLD'] as $event => $row) {
            $body = $this->log('product', ['entity_id' => "{$row}.entity_id"]);

            if ($event === 'au') {
                $body = $this->onlyIfChanged($this->columnsExcept('catalog_product_entity_tier_price', ['value_id']), $body);
            }

            $this->createTrigger('catalog_product_entity_tier_price', $event, $body);
        }

        foreach (['ai' => 'NEW', 'ad' => 'OLD'] as $event => $row) {
            $this->createTrigger('catalog_product_website', $event, implode("\n", [
                $this->log('product', ['entity_id' => "{$row}.product_id"]),
                $this->log('category', ['entity_id' => 'category_id'], "catalog_category_product WHERE product_id = {$row}.product_id"),
            ]));

            $this->createTrigger('catalog_product_relation', $event, $this->log('product', ['entity_id' => "{$row}.parent_id"]));
            $this->createTrigger('catalog_product_super_link', $event, $this->log('product', ['entity_id' => "{$row}.parent_id"]));
        }

        $this->createTrigger('cataloginventory_stock_item', 'ai', $this->log('product', ['entity_id' => 'NEW.product_id']));
        $this->createTrigger('cataloginventory_stock_item', 'au', implode("\n", [
            $this->onlyIfChanged(
                $this->columnsExcept('cataloginventory_stock_item', ['item_id', 'low_stock_date']),
                $this->log('product', ['entity_id' => 'NEW.product_id']),
            ),
            $this->onlyIfChanged(
                ['is_in_stock'],
                $this->log('category', ['entity_id' => 'category_id'], 'catalog_category_product WHERE product_id = NEW.product_id'),
            ),
        ]));
    }

    protected function createCategoryTriggers(): void
    {
        $this->createTrigger('catalog_category_entity', 'ai', $this->log('category', ['entity_id' => 'NEW.entity_id']));
        $this->createTrigger('catalog_category_entity', 'au', $this->log('category', ['entity_id' => 'NEW.entity_id']));
        $this->createTrigger('catalog_category_entity', 'bd', implode("\n", [
            $this->log('category', ['entity_id' => 'OLD.entity_id']),
            $this->log('product', ['entity_id' => 'product_id'], 'catalog_category_product WHERE category_id = OLD.entity_id'),
        ]));
    }

    protected function createJunctionTriggers(): void
    {
        foreach (['ai' => 'NEW', 'ad' => 'OLD'] as $event => $row) {
            $this->createTrigger('catalog_category_product', $event, implode("\n", [
                $this->log('category', ['entity_id' => "{$row}.category_id"]),
                $this->log('product', ['entity_id' => "{$row}.product_id"]),
            ]));
        }

        $this->createTrigger('catalog_category_product', 'au', $this->onlyIfChanged(
            ['position'],
            $this->log('category', ['entity_id' => 'NEW.category_id']),
        ));
    }

    protected function createAttributeTriggers(): void
    {
        $this->createTrigger('eav_attribute', 'au', $this->onlyIfChanged(
            $this->columnsExcept('eav_attribute', ['attribute_id']),
            $this->log('attribute', ['entity_id' => 'NEW.attribute_id']),
        ));
        $this->createTrigger('eav_attribute', 'bd', implode("\n", [
            $this->log('attribute', ['entity_id' => 'OLD.attribute_id']),
            $this->log('attribute', ['entity_id' => 'attribute_id', 'attribute_set_id' => 'attribute_set_id'], 'eav_entity_attribute WHERE attribute_id = OLD.attribute_id'),
        ]));

        $this->createTrigger('catalog_eav_attribute', 'au', $this->onlyIfChanged(
            $this->columnsExcept('catalog_eav_attribute', ['attribute_id']),
            $this->log('attribute', ['entity_id' => 'NEW.attribute_id']),
        ));

        foreach (['ai' => 'NEW', 'au' => 'NEW', 'ad' => 'OLD'] as $event => $row) {
            $label = $this->log('attribute', ['entity_id' => "{$row}.attribute_id", 'store_id' => "{$row}.store_id"]);
            $option = $this->log('attribute', ['entity_id' => "{$row}.attribute_id"]);
            $optionValue = $this->log('attribute', ['entity_id' => 'attribute_id', 'store_id' => "{$row}.store_id"], "eav_attribute_option WHERE option_id = {$row}.option_id");
            $swatch = $optionValue;
            $entityAttribute = $this->log('attribute', ['entity_id' => "{$row}.attribute_id", 'attribute_set_id' => "{$row}.attribute_set_id"]);

            if ($event === 'au') {
                $label = $this->onlyIfChanged(['value'], $label);
                $option = $this->onlyIfChanged(['sort_order'], $option);
                $optionValue = $this->onlyIfChanged(['value'], $optionValue);
                $swatch = $this->onlyIfChanged(['type', 'value'], $swatch);
                $entityAttribute = $this->onlyIfChanged($this->columnsExcept('eav_entity_attribute', ['entity_attribute_id']), $entityAttribute);
            }

            $this->createTrigger('eav_attribute_label', $event, $label);
            $this->createTrigger('eav_attribute_option', $event, $option);
            $this->createTrigger('eav_attribute_option_value', $event, $optionValue);
            $this->createTrigger('eav_attribute_option_swatch', $event, $swatch);
            $this->createTrigger('eav_entity_attribute', $event, $entityAttribute);
        }
    }

    protected function createTrigger(string $table, string $event, string $body): void
    {
        $body = str_replace(':trigger', "'{$table}_{$event}'", $body);

        DB::unprepared("
            CREATE TRIGGER rapidez_changelog_{$table}_{$event} {$this->events[$event]} ON {$table}
            FOR EACH ROW
            BEGIN
                {$body}
            END
        ");
    }

    protected function log(string $changelog, array $values, ?string $from = null): string
    {
        $values['`trigger`'] = ':trigger';
        $columns = implode(', ', array_keys($values));
        $values = implode(', ', $values);

        return $from
            ? "INSERT INTO rapidez_{$changelog}_changelog ({$columns}) SELECT {$values} FROM {$from};"
            : "INSERT INTO rapidez_{$changelog}_changelog ({$columns}) VALUES ({$values});";
    }

    protected function onlyIfChanged(array $columns, string $body): string
    {
        $condition = collect($columns)
            ->map(fn (string $column) => "NOT (NEW.`{$column}` <=> OLD.`{$column}`)")
            ->implode(' OR ');

        return "IF {$condition} THEN {$body} END IF;";
    }

    protected function columnsExcept(string $table, array $except): array
    {
        return array_values(array_diff(Schema::getColumnListing($table), $except));
    }

    protected function statusVisibilityAttributeIds(): ?string
    {
        $attributeIds = DB::table('eav_attribute')
            ->join('eav_entity_type', 'eav_entity_type.entity_type_id', '=', 'eav_attribute.entity_type_id')
            ->where('eav_entity_type.entity_type_code', 'catalog_product')
            ->whereIn('eav_attribute.attribute_code', ['status', 'visibility'])
            ->pluck('eav_attribute.attribute_id');

        return $attributeIds->isEmpty() ? null : $attributeIds->implode(', ');
    }
};
