<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

final class SchemaGenerateCommand extends BaseCommand
{
    protected $signature = 'ude:schema:generate {table : Tabla origen} {--connection= : Conexión} {--write : Escribir archivos (requiere confirmación previa)} {--force : Sobrescribir sin backup}';

    protected $description = 'Genera esqueleto de modelo y migración a partir del esquema real (nunca sobrescribe sin --force)';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);
        $definition = $inspector->table(new TableIdentifier($this->argument('table')));

        if ($definition === null) {
            $this->components->error('Tabla no encontrada.');

            return self::FAILURE;
        }

        $model = ucfirst((string) preg_replace_callback('/_(\w)/', fn ($m) => strtoupper($m[1]), rtrim($definition->name, 's')));
        $timestamp = date('Y_m_d_His');

        $migration = $this->migrationSkeleton($definition);
        $modelSkeleton = $this->modelSkeleton($model, $definition->name);

        if (! $this->option('write')) {
            $this->components->info('Dry run. Usa --write para escribir los archivos (se aplicará --force solo si lo indicas).');
            $this->line($migration);
            $this->newLine();
            $this->line($modelSkeleton);

            return self::SUCCESS;
        }

        $migrationPath = database_path('migrations/'.$timestamp.'_create_'.$definition->name.'_table.php');
        $modelPath = app_path('Models/'.$model.'.php');

        if ((file_exists($migrationPath) || file_exists($modelPath)) && ! $this->option('force')) {
            $this->components->error('Los archivos ya existen; usa --force tras respaldarlos.');

            return self::FAILURE;
        }

        file_put_contents($migrationPath, $migration);
        file_put_contents($modelPath, $modelSkeleton);

        $this->components->info('Migración: '.$migrationPath);
        $this->components->info('Modelo: '.$modelPath);

        return self::SUCCESS;
    }

    private function migrationSkeleton(TableDefinition $table): string
    {
        $lines = [];
        foreach ($table->columns as $column) {
            $method = $this->blueprintMethod($column->databaseType);
            $lines[] = "            \$table->{$method}('{$column->name}')".($column->nullable ? '->nullable()' : '').';';
        }

        $body = implode("\n", $lines);

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table->name}', function (Blueprint \$table) {
{$body}
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table->name}');
    }
};
PHP;
    }

    private function modelSkeleton(string $model, string $table): string
    {
        return <<<PHP
<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use OrmUniversal\\Laravel\\Concerns\\HasUniversalQueries;

class {$model} extends Model
{
    use HasUniversalQueries;

    protected \$table = '{$table}';
}
PHP;
    }

    private function blueprintMethod(string $databaseType): string
    {
        return match (true) {
            str_contains($databaseType, 'bigint') => 'bigInteger',
            str_contains($databaseType, 'int') => 'integer',
            str_contains($databaseType, 'decimal') => 'decimal',
            str_contains($databaseType, 'bool') => 'boolean',
            str_contains($databaseType, 'timestamp') => 'timestamp',
            str_contains($databaseType, 'datetime') => 'dateTime',
            str_contains($databaseType, 'date') => 'date',
            str_contains($databaseType, 'text') => 'text',
            str_contains($databaseType, 'json') => 'json',
            default => 'string',
        };
    }
}
