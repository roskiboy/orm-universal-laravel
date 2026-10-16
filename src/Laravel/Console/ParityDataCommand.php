<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use OrmUniversal\Laravel\Facades\OrmUniversal;

final class ParityDataCommand extends BaseCommand
{
    protected $signature = 'ude:parity:data
        {model : Clase del modelo Eloquent (p. ej. App\\Models\\BankMovement)}
        {--sample=50 : Filas a comparar}
        {--connection= : Conexión Laravel a usar}
        {--json : Emitir JSON en consola}';

    protected $description = 'Compara fila a fila los datos de Eloquent vanilla contra UDE para el mismo modelo y tabla';

    public function handle(): int
    {
        $modelClass = (string) $this->argument('model');

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            $this->components->error('El argumento model debe ser una clase Eloquent existente: '.$modelClass);

            return self::FAILURE;
        }

        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $sample = max(1, (int) $this->option('sample'));

        /** @var Model $probe */
        $probe = new $modelClass;
        $probe->setConnection($connection);

        $table = $probe->getTable();
        $key = $probe->getKeyName();

        $vanilla = [];
        foreach ($probe->newQuery()->withoutGlobalScope(SoftDeletingScope::class)->orderBy($key)->limit($sample)->get() as $model) {
            if ($model instanceof Model) {
                $vanilla[] = self::normalize($model->attributesToArray());
            }
        }

        $ude = array_map(
            static fn (array $row): array => self::normalize($row),
            OrmUniversal::table($table, $connection)
                ->orderBy($key)
                ->limit($sample)
                ->get(),
        );

        $vanillaCount = (int) DB::connection($connection)->table($table)->count();
        $udeCount = OrmUniversal::table($table, $connection)->count();

        $mismatches = [];
        $compared = min(count($vanilla), count($ude));

        for ($i = 0; $i < $compared; $i++) {
            if ($vanilla[$i] !== $ude[$i]) {
                $mismatches[] = ['index' => $i, 'vanilla' => $vanilla[$i], 'ude' => $ude[$i]];
            }
        }

        if (count($vanilla) !== count($ude)) {
            $mismatches[] = [
                'index' => 'row-count',
                'vanilla' => count($vanilla),
                'ude' => count($ude),
            ];
        }

        $report = [
            'model' => $modelClass,
            'table' => $table,
            'connection' => $connection,
            'sample' => $sample,
            'rows_compared' => $compared,
            'counts' => ['vanilla' => $vanillaCount, 'ude' => $udeCount],
            'counts_match' => $vanillaCount === $udeCount,
            'mismatches' => $mismatches,
            'status' => $mismatches === [] && $vanillaCount === $udeCount ? 'ok' : 'mismatch',
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $report['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info(sprintf(
            'Paridad de datos %s (%s.%s) — muestra %d, conteo vanilla %d vs ude %d',
            $modelClass,
            $connection,
            $table,
            $sample,
            $vanillaCount,
            $udeCount,
        ));

        if ($report['status'] === 'ok') {
            $this->components->info(sprintf('OK: %d filas idénticas fila a fila y conteo consistente.', $compared));

            return self::SUCCESS;
        }

        foreach ($mismatches as $mismatch) {
            $this->components->error('Fila '.$mismatch['index'].' divergente:');
            $this->line('  vanilla: '.(string) json_encode($mismatch['vanilla'], JSON_UNESCAPED_UNICODE));
            $this->line('  ude:     '.(string) json_encode($mismatch['ude'], JSON_UNESCAPED_UNICODE));
        }

        return self::FAILURE;
    }

    /**
     * Stable for comparison: sorted keys, dates/bools/numbers/JSON
     * normalized to a common canonical form between Eloquent and PDO.
     *
     * @param  array<string, mixed>|array<int, mixed>  $row
     * @return array<int|string, mixed>
     */
    private static function normalize(array $row): array
    {
        ksort($row);

        $normalized = [];
        foreach ($row as $column => $value) {
            $normalized[$column] = self::normalizeValue($value);
        }

        return $normalized;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            ksort($value);

            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return self::normalizeNumber($value);
        }

        $string = (string) $value;

        if (is_numeric($string)) {
            return self::normalizeNumber((float) $string);
        }

        $trimmed = trim($string);

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                ksort($decoded);

                return (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}:\d{2}))?/', $trimmed, $matches) === 1) {
            $date = $matches[1];
            $time = $matches[2] ?? '';

            return $time === '' || $time === '00:00:00' ? $date : $date.' '.$time;
        }

        return $string;
    }

    private static function normalizeNumber(float $number): string
    {
        if (abs($number) >= 1.0e15) {
            return (string) $number;
        }

        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.') ?: '0';
    }
}
