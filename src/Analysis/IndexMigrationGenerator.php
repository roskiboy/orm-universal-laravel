<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

/**
 * Turns index advice into a Laravel migration file body. Pure string builder so
 * it can be unit tested; the command decides when to write it.
 *
 * @api public
 */
final class IndexMigrationGenerator
{
    /**
     * @param  list<array{table: string, column: string, reason: string, suggestion: string}>  $advice
     */
    public function generate(array $advice): string
    {
        $byTable = [];
        foreach ($advice as $item) {
            $byTable[$item['table']][] = $item['column'];
        }

        $up = [];
        $down = [];
        foreach ($byTable as $table => $columns) {
            $indexLines = [];
            $dropLines = [];
            foreach (array_values(array_unique($columns)) as $column) {
                $indexName = $table.'_'.$column.'_idx';
                $indexLines[] = "            \$table->index(['".$column."'], '".$indexName."');";
                $dropLines[] = "            \$table->dropIndex('".$indexName."');";
            }

            $up[] = "        Schema::table('".$table."', function (Blueprint \$table): void {\n".implode("\n", $indexLines)."\n        });";
            $down[] = "        Schema::table('".$table."', function (Blueprint \$table): void {\n".implode("\n", $dropLines)."\n        });";
        }

        return "<?php\n\n"
            ."declare(strict_types=1);\n\n"
            ."use Illuminate\\Database\\Migrations\\Migration;\n"
            ."use Illuminate\\Database\\Schema\\Blueprint;\n"
            ."use Illuminate\\Support\\Facades\\Schema;\n\n"
            ."return new class extends Migration\n"
            ."{\n"
            ."    public function up(): void\n"
            ."    {\n"
            .implode("\n\n", $up)."\n"
            ."    }\n\n"
            ."    public function down(): void\n"
            ."    {\n"
            .implode("\n\n", $down)."\n"
            ."    }\n"
            ."};\n";
    }

    public function filename(string $timestamp): string
    {
        return $timestamp.'_add_ude_recommended_indexes.php';
    }
}
