<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function contracts_basic_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== contracts/basic: CapabilitySet + DialectVersion ==\n";

$capabilities = CapabilitySet::of([Capability::RETURNING, Capability::CTE]);

contracts_basic_assert($capabilities->supports(Capability::RETURNING), 'supports() lee una capacidad presente');
contracts_basic_assert(! $capabilities->supports(Capability::JSONB), 'supports() devuelve false para una ausente');
contracts_basic_assert(
    $capabilities->missing([Capability::JSONB, Capability::CTE]) === [Capability::JSONB],
    'missing() lista solo lo ausente',
);
contracts_basic_assert(CapabilitySet::empty()->all() === [], 'empty() no tiene capacidades');

$extended = $capabilities->with(Capability::JSONB, ['limit' => 100])->without(Capability::CTE);
contracts_basic_assert($extended->supports(Capability::JSONB), 'with() agrega una capacidad');
contracts_basic_assert(! $extended->supports(Capability::CTE), 'without() elimina una capacidad');
contracts_basic_assert($extended->value(Capability::JSONB) === ['limit' => 100], 'value() devuelve el valor asociado');
contracts_basic_assert(
    $capabilities->merge(CapabilitySet::of(['supportsDistinct']))->supports('supportsDistinct'),
    'merge() combina capacidades',
);
contracts_basic_assert(
    $capabilities->diff(CapabilitySet::of([Capability::CTE]))->all() === [Capability::RETURNING => true],
    'diff() conserva solo lo propio',
);

$capabilities->requireAll([Capability::RETURNING], 'sqlite', '3.49.2');
echo "[OK] requireAll() no lanza cuando la capacidad existe\n";

$version = DialectVersion::parse('sqlite', '3.49.2');

contracts_basic_assert((string) $version === 'sqlite 3.49.2', '__toString() formatea vendor + version');
contracts_basic_assert($version->major() === 3 && $version->minor() === 49 && $version->patch() === 2, 'componentes major/minor/patch');
contracts_basic_assert($version->numeric() === '3.49.2', 'numeric() normaliza a major.minor.patch');
contracts_basic_assert($version->atLeast(3, 35), 'atLeast() compara versiones (RETURNING desde 3.35)');
contracts_basic_assert(! $version->lessThan(3, 38), 'lessThan() compara versiones');
contracts_basic_assert($version->sameLine(DialectVersion::parse(DialectVendor::Sqlite, '3.35.0')), 'sameLine() compara vendor + major');
contracts_basic_assert(DialectVersion::parse('mariadb', '11.6.2')->atLeast(10, 5), 'parse() acepta vendor como string');

echo "contracts/basic OK\n";
exit(0);
