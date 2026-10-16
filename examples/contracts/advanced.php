<?php

declare(strict_types=1);

use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function contracts_advanced_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== contracts/advanced: QueryFingerprint ==\n";

$a = QueryFingerprint::fromSql('select * from "orders" where total >= 100 and status = \'paid\'', 2);
$b = QueryFingerprint::fromSql('select * from "orders" where total >= 999 and status = \'refunded\'', 2);

contracts_advanced_assert(
    $a->normalizedSql === 'select * from ? where total >= ? and status = ?',
    'normalize() reemplaza literales y colapsa espacios',
);
contracts_advanced_assert($a->bindingCount === 2, 'bindingCount se conserva');
contracts_advanced_assert($a->hash === hash('sha256', $a->normalizedSql), 'hash es sha256 del SQL normalizado');
contracts_advanced_assert($a->equals($b), 'misma forma + mismo bindingCount => fingerprints equivalentes');
contracts_advanced_assert((string) $a === $a->hash, '__toString() devuelve el hash');

$differentShape = QueryFingerprint::fromSql('select id from "orders" where total >= 100', 1);
contracts_advanced_assert(! $a->equals($differentShape), 'forma distinta => fingerprints distintos');

$unquoted = QueryFingerprint::fromSql('select * from orders where total >= 100 and status = \'paid\'', 2);
contracts_advanced_assert(! $a->equals($unquoted), 'el quoting forma parte de la identidad');

$withStructure = QueryFingerprint::fromSql('select * from "orders" where total >= ?', 1, 'ast:select:orders');
contracts_advanced_assert($withStructure->structuralHash === 'ast:select:orders', 'structuralHash opcional se conserva');

contracts_advanced_assert(
    QueryFingerprint::normalize("SELECT\n  *\nFROM \"orders\"") === 'select * from ?',
    'normalize() es estático y público',
);

echo "contracts/advanced OK\n";
exit(0);
