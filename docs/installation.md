# Instalación — orm-universal/laravel

## Requisitos

| Requisito | Versión |
|---|---|
| PHP | `^8.3` |
| Laravel (`illuminate/*`) | `^12.0 \|\| ^13.0` |
| `orm-universal/contracts` | `^0.1` (dependencia) |
| `orm-universal/relational` | `^0.1` (dependencia) |
| `orm-universal/nosql` | `^0.1` (dependencia) |
| Composer | 2.x |

El paquete incluye auto-discovery del service provider y del alias de fachada.

## Instalación

```bash
composer require orm-universal/laravel
php artisan ude:install
```

`ude:install` publica la configuración y ejecuta `ude:doctor` para diagnosticar entorno,
extensiones, conexión, dialecto y capacidades.

## Publicar la configuración

La publicación se hace con el tag `orm-universal-config`:

```bash
php artisan vendor:publish --tag=orm-universal-config
```

Genera `config/orm-universal.php`. En el service provider la configuración se fusiona
con `mergeConfigFrom`, por lo que los valores por defecto existen aunque no publiques.

## Registro manual (sin auto-discovery)

```php
// config/app.php
'providers' => [
    OrmUniversal\Laravel\OrmUniversalServiceProvider::class,
],
'aliases' => [
    'OrmUniversal' => OrmUniversal\Laravel\Facades\OrmUniversal::class,
],
```

## Instalación desde el monorepo (path repository)

```json
{
  "repositories": [
    { "type": "path", "url": "packages/contracts" },
    { "type": "path", "url": "packages/relational" },
    { "type": "path", "url": "packages/nosql" },
    { "type": "path", "url": "packages/laravel" }
  ],
  "require": {
    "orm-universal/laravel": "^0.1"
  }
}
```

## Drivers opcionales

- MongoDB: `ext-mongodb` + `mongodb/mongodb:^2.4`, habilitado con
  `UDE_MONGODB_ENABLED=true`.
- Redis: `ext-redis`, habilitado con `UDE_REDIS_ENABLED=true` (por defecto `false`).

`InMemoryDriver` se registra por defecto (`UDE_IN_MEMORY_ENABLED=true`).

## Verificación

```bash
php artisan ude:doctor
php artisan ude:compatibility
composer validate
vendor/bin/phpunit
vendor/bin/phpstan analyse
```
