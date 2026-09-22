<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EAV value tables that carry attributes for `catalog_product_entity` / `catalog_category_entity`.
     * Each gets an identical pair of AFTER INSERT / AFTER UPDATE triggers.
     */
    protected array $productEavTables = [
        'catalog_product_entity_int',
        'catalog_product_entity_decimal',
        'catalog_product_entity_varchar',
        'catalog_product_entity_text',
        'catalog_product_entity_datetime',
    ];

    protected array $categoryEavTables = [
        'catalog_category_entity_int',
        'catalog_category_entity_decimal',
        'catalog_category_entity_varchar',
        'catalog_category_entity_text',
        'catalog_category_entity_datetime',
    ];

    public function up(): void
    {
        $this->createChangelogTables();
        $this->createProductTriggers();
        $this->createCategoryTriggers();
        $this->createJunctionTriggers();
        $this->createStatusVisibilityFanInTrigger();
        $this->createAttributeDefinitionTriggers();
    }

    public function down(): void
    {
        foreach ($this->allTriggerNames() as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS rapidez_{$trigger}");
        }

        Schema::dropIfExists('rapidez_product_changelog');
        Schema::dropIfExists('rapidez_category_changelog');
    }

    protected function createChangelogTables(): void
    {
        Schema::create('rapidez_product_changelog', function (Blueprint $table) {
            $table->id('version_id');
            $table->unsignedInteger('entity_id');
            $table->string('trigger');
            $table->timestamp('created_at')->useCurrent();
            $table->index('entity_id');
            $table->index('created_at');
        });

        Schema::create('rapidez_category_changelog', function (Blueprint $table) {
            $table->id('version_id');
            $table->unsignedInteger('entity_id');
            $table->string('trigger');
            $table->timestamp('created_at')->useCurrent();
            $table->index('entity_id');
            $table->index('created_at');
        });
    }

    protected function createProductTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_product_entity_ai AFTER INSERT ON catalog_product_entity
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, \'catalog_product_entity_ai\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_product_entity_au AFTER UPDATE ON catalog_product_entity
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, \'catalog_product_entity_au\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_product_entity_ad AFTER DELETE ON catalog_product_entity
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (OLD.entity_id, \'catalog_product_entity_ad\')
        ');

        foreach ($this->productEavTables as $table) {
            DB::unprepared("
                CREATE TRIGGER rapidez_{$table}_ai AFTER INSERT ON {$table}
                FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, '{$table}_ai')
            ");

            DB::unprepared("
                CREATE TRIGGER rapidez_{$table}_au AFTER UPDATE ON {$table}
                FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, '{$table}_au')
            ");
        }

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_product_website_ai AFTER INSERT ON catalog_product_website
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.product_id, \'catalog_product_website_ai\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_product_website_ad AFTER DELETE ON catalog_product_website
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (OLD.product_id, \'catalog_product_website_ad\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_cataloginventory_stock_item_au AFTER UPDATE ON cataloginventory_stock_item
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.product_id, \'cataloginventory_stock_item_au\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_product_super_link_ai AFTER INSERT ON catalog_product_super_link
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.parent_id, \'catalog_product_super_link_ai\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_product_super_link_ad AFTER DELETE ON catalog_product_super_link
            FOR EACH ROW INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (OLD.parent_id, \'catalog_product_super_link_ad\')
        ');
    }

    protected function createCategoryTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_category_entity_ai AFTER INSERT ON catalog_category_entity
            FOR EACH ROW INSERT INTO rapidez_category_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, \'catalog_category_entity_ai\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_category_entity_au AFTER UPDATE ON catalog_category_entity
            FOR EACH ROW INSERT INTO rapidez_category_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, \'catalog_category_entity_au\')
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_category_entity_ad AFTER DELETE ON catalog_category_entity
            FOR EACH ROW INSERT INTO rapidez_category_changelog (entity_id, `trigger`) VALUES (OLD.entity_id, \'catalog_category_entity_ad\')
        ');

        foreach ($this->categoryEavTables as $table) {
            DB::unprepared("
                CREATE TRIGGER rapidez_{$table}_ai AFTER INSERT ON {$table}
                FOR EACH ROW INSERT INTO rapidez_category_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, '{$table}_ai')
            ");

            DB::unprepared("
                CREATE TRIGGER rapidez_{$table}_au AFTER UPDATE ON {$table}
                FOR EACH ROW INSERT INTO rapidez_category_changelog (entity_id, `trigger`) VALUES (NEW.entity_id, '{$table}_au')
            ");
        }
    }

    protected function createJunctionTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_category_product_ai AFTER INSERT ON catalog_category_product
            FOR EACH ROW BEGIN
                INSERT INTO rapidez_category_changelog (entity_id, `trigger`) VALUES (NEW.category_id, \'catalog_category_product_ai\');
                INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (NEW.product_id, \'catalog_category_product_ai\');
            END
        ');

        DB::unprepared('
            CREATE TRIGGER rapidez_catalog_category_product_ad AFTER DELETE ON catalog_category_product
            FOR EACH ROW BEGIN
                INSERT INTO rapidez_category_changelog (entity_id, `trigger`) VALUES (OLD.category_id, \'catalog_category_product_ad\');
                INSERT INTO rapidez_product_changelog (entity_id, `trigger`) VALUES (OLD.product_id, \'catalog_category_product_ad\');
            END
        ');
    }

    /**
     * A product's status/visibility can flip a category between empty and non-empty
     * without ever touching `catalog_category_product`, so this reaches into every
     * category the product belongs to whenever either attribute changes.
     */
    protected function createStatusVisibilityFanInTrigger(): void
    {
        $productEntityTypeId = DB::table('eav_entity_type')
            ->where('entity_type_code', 'catalog_product')
            ->value('entity_type_id');

        $statusAttributeId = (int) DB::table('eav_attribute')
            ->where('entity_type_id', $productEntityTypeId)
            ->where('attribute_code', 'status')
            ->value('attribute_id');

        $visibilityAttributeId = (int) DB::table('eav_attribute')
            ->where('entity_type_id', $productEntityTypeId)
            ->where('attribute_code', 'visibility')
            ->value('attribute_id');

        if (! $statusAttributeId || ! $visibilityAttributeId) {
            return;
        }

        DB::unprepared("
            CREATE TRIGGER rapidez_catalog_product_entity_int_category_fanin AFTER UPDATE ON catalog_product_entity_int
            FOR EACH ROW
            BEGIN
                IF NEW.attribute_id IN ({$statusAttributeId}, {$visibilityAttributeId}) THEN
                    INSERT INTO rapidez_category_changelog (entity_id, `trigger`)
                    SELECT category_id, 'catalog_product_entity_int_category_fanin'
                    FROM catalog_category_product WHERE product_id = NEW.entity_id;
                END IF;
            END
        ");
    }

    /**
     * Attribute set membership and attribute definition changes affect every product
     * whose attribute set includes that attribute, so these resolve products via
     * `eav_entity_attribute` -> `catalog_product_entity.attribute_set_id` instead of
     * scanning the EAV value tables.
     */
    protected function createAttributeDefinitionTriggers(): void
    {
        DB::unprepared("
            CREATE TRIGGER rapidez_eav_entity_attribute_ai AFTER INSERT ON eav_entity_attribute
            FOR EACH ROW
            INSERT INTO rapidez_product_changelog (entity_id, `trigger`)
            SELECT entity_id, 'eav_entity_attribute_ai'
            FROM catalog_product_entity WHERE attribute_set_id = NEW.attribute_set_id
        ");

        DB::unprepared("
            CREATE TRIGGER rapidez_eav_entity_attribute_ad AFTER DELETE ON eav_entity_attribute
            FOR EACH ROW
            INSERT INTO rapidez_product_changelog (entity_id, `trigger`)
            SELECT entity_id, 'eav_entity_attribute_ad'
            FROM catalog_product_entity WHERE attribute_set_id = OLD.attribute_set_id
        ");

        DB::unprepared("
            CREATE TRIGGER rapidez_catalog_eav_attribute_au AFTER UPDATE ON catalog_eav_attribute
            FOR EACH ROW
            BEGIN
                IF NEW.is_visible_on_front <> OLD.is_visible_on_front
                    OR NEW.used_in_product_listing <> OLD.used_in_product_listing
                    OR NEW.is_visible <> OLD.is_visible THEN
                    INSERT INTO rapidez_product_changelog (entity_id, `trigger`)
                    SELECT cpe.entity_id, 'catalog_eav_attribute_au'
                    FROM eav_entity_attribute eea
                    JOIN catalog_product_entity cpe ON cpe.attribute_set_id = eea.attribute_set_id
                    WHERE eea.attribute_id = NEW.attribute_id;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER rapidez_eav_attribute_label_ai AFTER INSERT ON eav_attribute_label
            FOR EACH ROW
            INSERT INTO rapidez_product_changelog (entity_id, `trigger`)
            SELECT cpe.entity_id, 'eav_attribute_label_ai'
            FROM eav_entity_attribute eea
            JOIN catalog_product_entity cpe ON cpe.attribute_set_id = eea.attribute_set_id
            WHERE eea.attribute_id = NEW.attribute_id
        ");

        DB::unprepared("
            CREATE TRIGGER rapidez_eav_attribute_label_au AFTER UPDATE ON eav_attribute_label
            FOR EACH ROW
            INSERT INTO rapidez_product_changelog (entity_id, `trigger`)
            SELECT cpe.entity_id, 'eav_attribute_label_au'
            FROM eav_entity_attribute eea
            JOIN catalog_product_entity cpe ON cpe.attribute_set_id = eea.attribute_set_id
            WHERE eea.attribute_id = NEW.attribute_id
        ");
    }

    protected function allTriggerNames(): array
    {
        $triggers = [
            'catalog_product_entity_ai',
            'catalog_product_entity_au',
            'catalog_product_entity_ad',
            'catalog_product_website_ai',
            'catalog_product_website_ad',
            'cataloginventory_stock_item_au',
            'catalog_product_super_link_ai',
            'catalog_product_super_link_ad',
            'catalog_category_entity_ai',
            'catalog_category_entity_au',
            'catalog_category_entity_ad',
            'catalog_category_product_ai',
            'catalog_category_product_ad',
            'catalog_product_entity_int_category_fanin',
            'eav_entity_attribute_ai',
            'eav_entity_attribute_ad',
            'catalog_eav_attribute_au',
            'eav_attribute_label_ai',
            'eav_attribute_label_au',
        ];

        foreach ($this->productEavTables as $table) {
            $triggers[] = "{$table}_ai";
            $triggers[] = "{$table}_au";
        }

        foreach ($this->categoryEavTables as $table) {
            $triggers[] = "{$table}_ai";
            $triggers[] = "{$table}_au";
        }

        return $triggers;
    }
};
