<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Laravel\Analysis\IndexAdvisor;
use OrmUniversal\Laravel\Analysis\IndexMigrationGenerator;
use PHPUnit\Framework\TestCase;

final class IndexAdvisorTest extends TestCase
{
    public function test_advises_uncovered_foreign_keys_and_id_columns(): void
    {
        $definition = new TableDefinition(
            'orders',
            columns: [
                new ColumnDefinition('id', 'bigint'),
                new ColumnDefinition('customer_id', 'bigint'),
                new ColumnDefinition('order_id', 'bigint'),
                new ColumnDefinition('status', 'varchar'),
            ],
            indexes: [new IndexDefinition('orders_status_index', ['status'])],
            foreignKeys: [new ForeignKeyDefinition('fk_customer', ['customer_id'], 'customers', ['id'])],
        );

        $advice = (new IndexAdvisor)->adviseTable('orders', $definition);

        self::assertSame(['customer_id', 'order_id'], array_column($advice, 'column'));
        self::assertStringContainsString('CREATE INDEX orders_customer_id_idx', $advice[0]['suggestion']);
        self::assertStringContainsString('fk_customer', $advice[0]['reason']);
    }

    public function test_covered_prefix_suppresses_foreign_key_advice(): void
    {
        $definition = new TableDefinition(
            'orders',
            columns: [new ColumnDefinition('customer_id', 'bigint')],
            indexes: [new IndexDefinition('orders_customer_status_index', ['customer_id', 'status'])],
            foreignKeys: [new ForeignKeyDefinition('fk_customer', ['customer_id'], 'customers', ['id'])],
        );

        self::assertSame([], (new IndexAdvisor)->adviseTable('orders', $definition));
    }
}

final class IndexMigrationGeneratorTest extends TestCase
{
    public function test_generates_one_schema_block_per_table(): void
    {
        $generator = new IndexMigrationGenerator;
        $content = $generator->generate([
            ['table' => 'orders', 'column' => 'customer_id', 'reason' => 'fk', 'suggestion' => ''],
            ['table' => 'orders', 'column' => 'order_id', 'reason' => 'id', 'suggestion' => ''],
            ['table' => 'bank_movements', 'column' => 'seller_id', 'reason' => 'id', 'suggestion' => ''],
        ]);

        self::assertStringContainsString("Schema::table('orders'", $content);
        self::assertStringContainsString("Schema::table('bank_movements'", $content);
        self::assertSame(2, substr_count($content, 'Schema::table('));
        self::assertStringContainsString("\$table->index(['customer_id'], 'orders_customer_id_idx');", $content);
        self::assertStringContainsString("\$table->index(['order_id'], 'orders_order_id_idx');", $content);
        self::assertStringContainsString("\$table->dropIndex('orders_customer_id_idx');", $content);
        self::assertStringContainsString('extends Migration', $content);
    }

    public function test_filename_uses_timestamp(): void
    {
        self::assertSame(
            '2026_10_07_120000_add_ude_recommended_indexes.php',
            (new IndexMigrationGenerator)->filename('2026_10_07_120000'),
        );
    }
}
