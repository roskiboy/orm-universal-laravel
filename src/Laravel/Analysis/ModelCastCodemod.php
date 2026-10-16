<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\ParserFactory;

/**
 * Surgical codemod for a model `$casts` property. Existing entries are replaced
 * byte by byte and new entries are inserted before the closing bracket; the rest
 * of the file is preserved verbatim. Models without a `$casts` property are
 * reported as manual edits instead of guessing an insertion point.
 *
 * @api public
 */
final class ModelCastCodemod
{
    /**
     * @return array{casts_property: bool, current: array<string, mixed>}
     */
    public function inspect(string $code): array
    {
        $array = $this->castsArray($code);

        if ($array === null) {
            return ['casts_property' => false, 'current' => []];
        }

        $current = [];
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->value instanceof String_ || $item->value instanceof Node\Scalar\Int_ || $item->value instanceof Node\Scalar\Float_) {
                $current[$item->key->value] = $item->value->value;
            }
        }

        return ['casts_property' => true, 'current' => $current];
    }

    /**
     * @param  list<array{column: string, suggested: string}>  $changes
     */
    public function apply(string $code, array $changes): ?string
    {
        $array = $this->castsArray($code);

        if ($array === null || $changes === []) {
            return null;
        }

        $existing = [];
        $closing = $array->getEndFilePos();

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            $existing[$item->key->value] = $item->value;
        }

        $replacements = [];
        $additions = [];
        $delta = 0;

        foreach ($changes as $change) {
            $column = $change['column'];
            $literal = var_export($change['suggested'], true);

            if (isset($existing[$column])) {
                $value = $existing[$column];
                $start = $value->getStartFilePos();
                $end = $value->getEndFilePos();

                if ($start < 0 || $end < $start) {
                    return null;
                }

                $replacements[] = ['start' => $start, 'end' => $end, 'literal' => $literal];
                $delta += strlen($literal) - ($end - $start + 1);

                continue;
            }

            $additions[$column] = $literal;
        }

        if ($replacements !== [] && $delta !== 0) {
            $closing += $delta;
        }

        usort($replacements, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($replacements as $replacement) {
            $code = substr_replace($code, $replacement['literal'], $replacement['start'], $replacement['end'] - $replacement['start'] + 1);
        }

        if ($additions !== []) {
            $indent = $this->indentation($code, $array);

            $insert = '';
            foreach ($additions as $column => $literal) {
                $insert .= PHP_EOL.$indent.var_export($column, true).' => '.$literal.',';
            }

            $code = substr_replace($code, $insert, $closing, 0);
        }

        return $code;
    }

    private function castsArray(string $code): ?Array_
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);

        if ($ast === null) {
            return null;
        }

        foreach ($ast as $statement) {
            if (! $statement instanceof Class_) {
                continue;
            }

            foreach ($statement->stmts as $member) {
                if (! $member instanceof Property || count($member->props) !== 1) {
                    continue;
                }

                if ($member->props[0]->name->toString() !== 'casts') {
                    continue;
                }

                $default = $member->props[0]->default;

                return $default instanceof Array_ ? $default : null;
            }
        }

        return null;
    }

    private function indentation(string $code, Array_ $array): string
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $lineStart = strrpos(substr($code, 0, $item->getStartFilePos()), PHP_EOL);

            if ($lineStart === false) {
                continue;
            }

            $line = substr($code, $lineStart + strlen(PHP_EOL), $item->getStartFilePos() - $lineStart - strlen(PHP_EOL));

            if (preg_match('/^\s+/', $line, $matches) === 1) {
                return $matches[0];
            }
        }

        return '        ';
    }
}
