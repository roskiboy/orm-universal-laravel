# Prompt: extender orm-universal/laravel (adapter, plugin o comando)

## Contexto

La capa Laravel es fina a propósito: provider, adapters, facade, puente Eloquent y comandos.
Para extenderla, sigue los patrones reales del paquete y no metas lógica de motores aquí.

## Objetivo

Pide a un agente que elija una vía:

### 1. Adapter nuevo

- Modela la clase en `src/Adapters/` siguiendo `LaravelConnectionAdapter`
  (`executor()`, `dialectFor()`, `summary()`, `flush()`).
- No dependas de Laravel en el Core: el adapter traduce, el motor ejecuta.

### 2. Plugin (dialecto/tipo/negocio)

- Implementa `OrmUniversal\Contracts\PluginInterface`:

```php
final class MyPlugin implements PluginInterface
{
    public function name(): string { return 'my-plugin'; }
    public function register(object $kernel): void { /* registra dialecto/tipo/scopes */ }
    public function boot(object $kernel): void { /* arranque diferido */ }
}
```

- El kernel (`Ude`) expone `plugins()`, y `bootPlugins()` devuelve los nombres registrados.

### 3. Comando `ude:*`

- Extiende `OrmUniversal\Laravel\Console\BaseCommand` (`kernel()`, `executor()`,
  `reportDirectory()`, `writeReport()`).
- Define `$signature` y `$description` en español, como los 16 existentes.
- Escribe reportes JSON + Markdown en `config('orm-universal.tooling.report_path')`
  (`storage/ude` por defecto).
- Regístralo en `OrmUniversalServiceProvider::boot()` dentro del bloque
  `runningInConsole()`.
- Regla dura: los comandos de análisis/codemod **jamás** escriben archivos de la app sin
  `--write` explícito y confirmación.

### 4. Config nueva

- Añade la clave en `config/orm-universal.php` con `env()` y documenta el default.
- Evita romper claves existentes (modo híbrido es el contrato de entrada).

## Reglas

- `declare(strict_types=1)`, una clase por archivo, doc en español.
- Sin lógica de compilación/conexión en esta capa.
- Tests en `OrmUniversal\Laravel\Tests\` con la app de test; verifica auto-discovery.

## Verificación

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
php artisan ude:doctor
```
