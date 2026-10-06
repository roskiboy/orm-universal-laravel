<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Laravel\Analysis\ProjectAnalyzer;
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

final class StubsCommand extends BaseCommand
{
    protected $signature = 'ude:stubs {--output= : Ruta del archivo generado}';

    protected $description = 'Genera stubs de IDE para macros, scopes y métodos dinámicos de UDE';

    public function handle(): int
    {
        $output = $this->option('output') ?: $this->reportDirectory().'/ide/_ide_helper_ude.php';
        $directory = dirname($output);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $analyzer = new ProjectAnalyzer(base_path(), (array) config('orm-universal.tooling.model_paths', ['app/Models']));
        $models = $analyzer->models();
        $usesTrait = [];

        foreach (array_keys($models) as $class) {
            if (class_exists($class) && in_array(HasUniversalQueries::class, class_uses_recursive($class), true)) {
                $usesTrait[] = $class;
            }
        }

        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            '/*',
            ' * Stubs generados por ude:stubs. No se cargan en runtime.',
            ' * Los métodos de UDE son dinámicos; este archivo ayuda a IDEs y analizadores.',
            ' */',
            '',
            'namespace UdeIde;',
            '',
            '/**',
            ' * @method static \\OrmUniversal\\Relational\\Builder\\QueryBuilder universal()',
            ' * @method static \\OrmUniversal\\Contracts\\RecordsetInterface recordset()',
            ' */',
            'final class EloquentBuilderMacros',
            '{',
            '}',
            '',
        ];

        foreach ($usesTrait as $class) {
            $lines[] = '/**';
            $lines[] = ' * @method static \\OrmUniversal\\Relational\\Builder\\QueryBuilder universal()';
            $lines[] = ' * @mixin \\'.$class;
            $lines[] = ' */';
            $lines[] = 'final class '.class_basename($class).'UdeStub';
            $lines[] = '{';
            $lines[] = '}';
            $lines[] = '';
        }

        file_put_contents($output, implode(PHP_EOL, $lines).PHP_EOL);

        $this->components->info('Stubs generados: '.$output);
        $this->components->bulletList([
            'Modelos con HasUniversalQueries: '.count($usesTrait),
            'Macros documentados: universal(), recordset()',
        ]);

        return self::SUCCESS;
    }
}
