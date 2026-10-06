<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class DocsApiCommand extends BaseCommand
{
    protected $signature = 'ude:docs:api {--output= : Directorio destino} {--package=* : Filtrar por paquete}';

    protected $description = 'Genera la referencia API en Markdown reflejando las clases públicas de los paquetes';

    public function handle(): int
    {
        $classmapFile = base_path('vendor/composer/autoload_classmap.php');

        if (! is_file($classmapFile)) {
            $this->components->error('Classmap no encontrado; ejecuta composer dump-autoload -o.');

            return self::FAILURE;
        }

        /** @var array<string, string> $classmap */
        $classmap = require $classmapFile;
        $filters = array_map('strtolower', (array) $this->option('package'));

        $classes = [];
        foreach (array_keys($classmap) as $class) {
            if (! str_starts_with($class, 'OrmUniversal\\') || str_contains($class, '\\Tests\\')) {
                continue;
            }

            $package = match (true) {
                str_starts_with($class, 'OrmUniversal\\Contracts\\') => 'contracts',
                str_starts_with($class, 'OrmUniversal\\Relational\\') => 'relational',
                str_starts_with($class, 'OrmUniversal\\NoSql\\') => 'nosql',
                str_starts_with($class, 'OrmUniversal\\Laravel\\') => 'laravel',
                default => null,
            };

            if ($package === null || ($filters !== [] && ! in_array($package, $filters, true))) {
                continue;
            }

            $classes[$package][] = $class;
        }

        $output = $this->option('output') ?: $this->reportDirectory().'/api';
        if (! is_dir($output)) {
            mkdir($output, 0775, true);
        }

        $written = [];
        foreach ($classes as $package => $packageClasses) {
            sort($packageClasses);
            $lines = ['# API Reference — '.$package, '', 'Generado: '.date('c'), ''];

            foreach ($packageClasses as $class) {
                $reflection = new ReflectionClass($class);
                if ($reflection->isInterface() || $reflection->isEnum() || str_starts_with($reflection->getShortName(), 'Abstract')) {
                    // Interfaces, enums and abstract bases are listed too: they are public API.
                }

                $doc = trim((string) $reflection->getDocComment());
                $summary = '';
                if ($doc !== '' && preg_match('/\*\s+(.+)/', $doc, $match) === 1) {
                    $summary = trim($match[1]);
                }

                $lines[] = '## '.$reflection->getShortName();
                $lines[] = '';
                $lines[] = '- FQCN: `'.$class.'`';
                $lines[] = '- Tipo: '.($reflection->isInterface() ? 'interface' : ($reflection->isEnum() ? 'enum' : ($reflection->isAbstract() ? 'abstract class' : 'class')));
                if ($summary !== '') {
                    $lines[] = '- Descripción: '.$summary;
                }
                $lines[] = '';

                if (! $reflection->isEnum()) {
                    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                        if ($method->getDeclaringClass()->getName() !== $class || str_starts_with($method->getName(), '__')) {
                            continue;
                        }

                        $parameters = [];
                        foreach ($method->getParameters() as $parameter) {
                            $type = $parameter->getType();
                            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
                            $parameters[] = ($parameter->isVariadic() ? '...' : '').'$'.$parameter->getName().': '.$typeName;
                        }

                        $return = $method->getReturnType();
                        $lines[] = '- `'.($method->isStatic() ? 'static ' : '').$method->getName().'('.implode(', ', $parameters).'): '
                            .($return instanceof ReflectionNamedType ? $return->getName() : 'mixed').'`';
                    }
                    $lines[] = '';
                }
            }

            file_put_contents($output.'/'.$package.'.md', implode(PHP_EOL, $lines).PHP_EOL);
            $written[] = $output.'/'.$package.'.md';
        }

        $this->components->info('Referencia API generada:');
        $this->components->bulletList($written ?: ['(sin clases)']);

        return self::SUCCESS;
    }
}
