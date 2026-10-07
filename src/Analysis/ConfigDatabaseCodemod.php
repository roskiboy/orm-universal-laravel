<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;

/**
 * Surgical codemod for `config/database.php`: finds the `driver` string
 * literals of every connection and rewrites them to `ude-*`. Only the string
 * literal bytes are replaced; the rest of the file is preserved verbatim.
 *
 * @api public
 */
final class ConfigDatabaseCodemod
{
    /** @var list<string> */
    public const DRIVERS = ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv', 'oracle'];

    /**
     * @return list<array{connection: string, current: string, proposed: string, start: int, end: int}>
     */
    public function analyze(string $code): array
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);

        if ($ast === null) {
            return [];
        }

        $connections = $this->connectionsArray($ast);
        if ($connections === null) {
            return [];
        }

        $edits = [];

        foreach ($connections->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_ || ! $item->value instanceof Array_) {
                continue;
            }

            $driver = $this->arrayItem($item->value, 'driver');
            if ($driver === null || ! $driver->value instanceof String_) {
                continue;
            }

            $current = $driver->value->value;
            $start = $driver->value->getStartFilePos();
            $end = $driver->value->getEndFilePos();

            if ($start < 0 || $end < $start) {
                continue;
            }

            if (str_starts_with($current, 'ude-') || ! in_array($current, self::DRIVERS, true)) {
                continue;
            }

            $edits[] = [
                'connection' => $item->key->value,
                'current' => $current,
                'proposed' => 'ude-'.$current,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $edits;
    }

    /**
     * @param  list<array{connection: string, current: string, proposed: string, start: int, end: int}>  $edits
     */
    public function apply(string $code, array $edits): string
    {
        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $code = substr_replace(
                $code,
                var_export($edit['proposed'], true),
                $edit['start'],
                $edit['end'] - $edit['start'] + 1,
            );
        }

        return $code;
    }

    /**
     * @param  list<Node>  $ast
     */
    private function connectionsArray(array $ast): ?Array_
    {
        foreach ($ast as $statement) {
            if (! $statement instanceof Return_ || ! $statement->expr instanceof Array_) {
                continue;
            }

            $connections = $this->arrayItem($statement->expr, 'connections');

            if ($connections !== null && $connections->value instanceof Array_) {
                return $connections->value;
            }
        }

        return null;
    }

    private function arrayItem(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === $key) {
                return $item;
            }
        }

        return null;
    }
}
