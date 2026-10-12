<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use Illuminate\Foundation\Application;
use OrmUniversal\Laravel\Analysis\ParityAnalyzer;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Migration\SchemaBuilder;

final class ParityApiCommand extends BaseCommand
{
    protected $signature = 'ude:parity:api {--output= : Directorio destino} {--json : Emitir JSON en consola}';

    protected $description = 'Compara la API de Laravel (Query Builder, Eloquent y Schema) contra UDE y genera el reporte de paridad';

    /**
     * @var array<class-string, list<class-string>>
     */
    private const COMPARISONS = [
        'Illuminate\Database\Query\Builder' => [
            QueryBuilder::class,
            SelectQuery::class,
        ],
        'Illuminate\Database\Eloquent\Builder' => [
            QueryBuilder::class,
            SelectQuery::class,
        ],
        'Illuminate\Database\Schema\Blueprint' => [
            SchemaBuilder::class,
        ],
    ];

    public function handle(): int
    {
        $analyzer = new ParityAnalyzer;
        $sources = [];
        $totals = [ParityAnalyzer::PARITY => 0, ParityAnalyzer::ALIAS => 0, ParityAnalyzer::NA => 0, ParityAnalyzer::DEFER => 0, ParityAnalyzer::GAP => 0];

        foreach (self::COMPARISONS as $source => $targets) {
            if (! class_exists($source)) {
                $this->components->warn('Clase Laravel no disponible: '.$source);

                continue;
            }

            $result = $analyzer->compare($source, $targets);
            $sources[] = $result;

            foreach ($totals as $type => $count) {
                $totals[$type] += $result['counts'][$type];
            }
        }

        $payload = [
            'generated_at' => date('c'),
            'laravel_version' => $this->laravelVersion(),
            'totals' => $totals,
            'sources' => $sources,
        ];

        $output = (string) ($this->option('output') ?: $this->reportDirectory());

        if (! is_dir($output)) {
            mkdir($output, 0775, true);
        }

        file_put_contents(
            $output.'/api-parity.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        file_put_contents($output.'/api-parity.md', $this->markdown($payload));

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->components->info('Paridad Laravel '.$payload['laravel_version'].' vs UDE');

        $rows = [];
        foreach ($sources as $source) {
            $rows[] = [
                class_basename($source['source']),
                $source['counts'][ParityAnalyzer::PARITY],
                $source['counts'][ParityAnalyzer::ALIAS],
                $source['counts'][ParityAnalyzer::NA],
                $source['counts'][ParityAnalyzer::DEFER],
                $source['counts'][ParityAnalyzer::GAP],
            ];
        }

        $this->table(['Clase Laravel', 'Paridad', 'Alias', 'N/A', 'Diferido', 'Gap'], $rows);

        foreach ($sources as $source) {
            if ($source['gap'] === []) {
                continue;
            }

            $this->components->warn(class_basename($source['source']).' — gaps reales ('.$source['counts'][ParityAnalyzer::GAP].'):');
            $this->components->bulletList($source['gap']);
        }

        $this->components->info('Reporte: '.$output.'/api-parity.{json,md}');

        return self::SUCCESS;
    }

    /**
     * @param  array{generated_at: string, laravel_version: string, totals: array<string, int>, sources: list<array<string, mixed>>}  $payload
     */
    private function markdown(array $payload): string
    {
        $lines = [
            '# Paridad Laravel '.$payload['laravel_version'].' vs UDE',
            '',
            'Generado: '.$payload['generated_at'],
            '',
            '## Resumen',
            '',
            '| Clase Laravel | Paridad | Alias | N/A | Diferido | Gap |',
            '|---|---:|---:|---:|---:|---:|',
        ];

        foreach ($payload['sources'] as $source) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d | %d | %d |',
                $source['source'],
                $source['counts'][ParityAnalyzer::PARITY],
                $source['counts'][ParityAnalyzer::ALIAS],
                $source['counts'][ParityAnalyzer::NA],
                $source['counts'][ParityAnalyzer::DEFER],
                $source['counts'][ParityAnalyzer::GAP],
            );
        }

        $lines[] = sprintf(
            '| **Total** | %d | %d | %d | %d | %d |',
            $payload['totals'][ParityAnalyzer::PARITY],
            $payload['totals'][ParityAnalyzer::ALIAS],
            $payload['totals'][ParityAnalyzer::NA],
            $payload['totals'][ParityAnalyzer::DEFER],
            $payload['totals'][ParityAnalyzer::GAP],
        );

        foreach ($payload['sources'] as $source) {
            $lines[] = '';
            $lines[] = '## '.$source['source'];
            $lines[] = '';
            $lines[] = 'Destinos: '.implode(', ', array_map('class_basename', $source['targets']));
            $lines[] = '';

            $this->section($lines, 'Gaps reales (pendientes)', $source['gap']);
            $this->section($lines, 'Diferidos (con razón)', $source['defer']);
            $this->section($lines, 'Alias', $source['alias']);
            $this->section($lines, 'No aplica', $source['na']);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>|array<string, string>  $items
     */
    private function section(array &$lines, string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        $lines[] = '### '.$title;
        $lines[] = '';

        foreach ($items as $key => $value) {
            $lines[] = is_int($key)
                ? '- `'.$value.'`'
                : '- `'.$key.'` → '.$value;
        }

        $lines[] = '';
    }

    private function laravelVersion(): string
    {
        return Application::VERSION;
    }
}
