# Prompt: extend OrmUniversal\Laravel (adapter, plugin, or command)

## Context

The Laravel layer is intentionally thin: provider, adapters, facade, Eloquent bridge, and
commands. To extend it, follow the real patterns of the package and do not put engine
logic here.

## Goal

Ask an agent to choose a path:

### 1. New adapter

- Model the class in `src/Adapters/` following `LaravelConnectionAdapter`
  (`executor()`, `dialectFor()`, `summary()`, `flush()`).
- Do not depend on Laravel in the Core: the adapter translates, the engine executes.

### 2. Plugin (dialect/type/business)

- Implement `OrmUniversal\Contracts\PluginInterface`:

```php
final class MyPlugin implements PluginInterface
{
    public function name(): string { return 'my-plugin'; }
    public function register(object $kernel): void { /* registers dialect/type/scopes */ }
    public function boot(object $kernel): void { /* deferred boot */ }
}
```

- The kernel (`Ude`) exposes `plugins()`, and `bootPlugins()` returns the registered names.

### 3. `ude:*` command

- Extend `OrmUniversal\Laravel\Console\BaseCommand` (`kernel()`, `executor()`,
  `reportDirectory()`, `writeReport()`).
- Define `$signature` and `$description` in Spanish, like the 16 existing ones.
- Write JSON + Markdown reports under `config('orm-universal.tooling.report_path')`
  (`storage/ude` by default).
- Register it in `OrmUniversalServiceProvider::boot()` inside the
  `runningInConsole()` block.
- Hard rule: analysis/codemod commands **never** write app files without an explicit
  `--write` and confirmation.

### 4. New config

- Add the key in `config/orm-universal.php` with `env()` and document the default.
- Avoid breaking existing keys (hybrid mode is the entry contract).

## Rules

- `declare(strict_types=1)`, one class per file, docs in Spanish.
- No compilation/connection logic in this layer.
- Tests in `OrmUniversal\Laravel\Tests\` with the test app; verify auto-discovery.

## Verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
php artisan ude:doctor
```
