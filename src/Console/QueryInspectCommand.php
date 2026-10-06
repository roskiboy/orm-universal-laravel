<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;

final class QueryInspectCommand extends BaseCommand
{
    protected $signature = 'ude:query:inspect {table : Tabla} {--connection= : Conexión} {--select=* : Columnas} {--where=* : Pares columna=valor} {--limit=10 : Límite} {--explain : Ejecutar EXPLAIN cuando el motor lo soporte}';

    protected $description = 'Compila e inspecciona una consulta: SQL, bindings, plan, fingerprint y capacidades';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));

        /** @var QueryBuilder $query */
        $query = QueryBuilder::table($executor, (string) $this->argument('table'));

        $columns = $this->option('select');
        if ($columns !== []) {
            $query->select(...$columns);
        }

        foreach ($this->option('where') as $condition) {
            [$column, $value] = array_pad(explode('=', (string) $condition, 2), 2, null);
            if ($column !== null) {
                $query->where(trim((string) $column), $value);
            }
        }

        $query->limit((int) $this->option('limit'));

        $compiled = $query->compile();

        $this->table(['Item', 'Value'], [
            ['Connection', $executor->connection()->driverName()],
            ['Dialect', $executor->dialect()->name()],
            ['SQL', $compiled->sql],
            ['Bindings', (string) json_encode($compiled->bindings, JSON_UNESCAPED_SLASHES)],
            ['Fingerprint', (string) $compiled->fingerprint()],
            ['Capabilities', implode(', ', $compiled->requiredCapabilities) ?: '(none)'],
        ]);

        $this->components->info('Plan:');
        $this->line((string) json_encode($query->toPlan(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->writeReport('query-inspect', [
            'sql' => $compiled->sql,
            'bindings' => $compiled->bindings,
            'fingerprint' => (string) $compiled->fingerprint(),
            'plan' => $query->toPlan(),
        ]);

        if ($this->option('explain') && $executor->dialect()->supports('supportsExplain')) {
            try {
                $rows = $executor->rows(new RawQuery('explain '.$compiled->sql, $compiled->bindings));
                $this->newLine();
                $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } catch (\Throwable $exception) {
                $this->components->warn('EXPLAIN no disponible: '.$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
