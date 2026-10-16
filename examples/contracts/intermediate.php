<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Enums\RawType;
use OrmUniversal\Contracts\ValueObjects\SqlFragment;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function contracts_intermediate_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== contracts/intermediate: SqlFragment + RawType ==\n";

$trusted = SqlFragment::trusted('lower(name)');
contracts_intermediate_assert($trusted->type === RawType::Trusted, 'trusted() declara RawType::Trusted');
contracts_intermediate_assert((string) $trusted === 'lower(name)', '__toString() devuelve el SQL');

$parameterized = SqlFragment::parameterized('coalesce(total, ?)', [0]);
contracts_intermediate_assert($parameterized->type === RawType::Parameterized, 'parameterized() declara bindings');
contracts_intermediate_assert($parameterized->bindings === [0], 'parameterized() conserva los bindings');

$identifier = SqlFragment::identifier('orders.total');
contracts_intermediate_assert($identifier->type === RawType::Identifier, 'identifier() declara un identificador');
contracts_intermediate_assert($identifier->bindings === [], 'identifier() no lleva bindings');

$value = SqlFragment::value(42);
contracts_intermediate_assert($value->type === RawType::Value, 'value() declara RawType::Value');
contracts_intermediate_assert($value->sql === '?' && $value->bindings === [42], 'value() viaja como placeholder + binding');

$caught = false;
try {
    new SqlFragment('?', RawType::Value);
} catch (InvalidArgumentException $exception) {
    $caught = true;
    echo '[OK] value sin bindings lanza InvalidArgumentException: '.$exception->getMessage()."\n";
}
contracts_intermediate_assert($caught, 'el constructor valida RawType::Value con bindings');

$fromConstructor = new SqlFragment('coalesce(total, ?)', RawType::Parameterized, [10]);
contracts_intermediate_assert($fromConstructor->bindings === [10], 'constructor directo respeta tipo y bindings');

echo "contracts/intermediate OK\n";
exit(0);
