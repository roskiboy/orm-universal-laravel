<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Compiler;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\CapabilityNegotiatorInterface;
use OrmUniversal\Contracts\DialectInterface;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Negotiates required capabilities against the dialect before compilation.
 * Equivalent capabilities are accepted as alternatives (e.g. GROUP_CONCAT vs
 * STRING_AGG) because the compiler maps the logical aggregate to whichever the
 * dialect provides.
 *
 * @api public
 */
final class CapabilityNegotiator implements CapabilityNegotiatorInterface
{
    /**
     * capability => list of alternatives that satisfy the same intent.
     *
     * @var array<string, list<string>>
     */
    private const ALTERNATIVES = [
        Capability::GROUP_CONCAT => [Capability::STRING_AGG],
        Capability::STRING_AGG => [Capability::GROUP_CONCAT],
        Capability::RETURNING => [Capability::OUTPUT_CLAUSE],
        Capability::INSERT_REPLACE => [Capability::UPSERT],
    ];

    /**
     * @return list<string>
     */
    public function requiredFor(AstNodeInterface $node): array
    {
        return CapabilityAnalyzer::forNode($node);
    }

    public function negotiate(AstNodeInterface $node, DialectInterface $dialect): void
    {
        $required = $this->requiredFor($node);
        $capabilities = $dialect->capabilities();
        $missing = [];

        foreach ($required as $capability) {
            if ($capabilities->supports($capability)) {
                continue;
            }

            $satisfied = false;
            foreach (self::ALTERNATIVES[$capability] ?? [] as $alternative) {
                if ($capabilities->supports($alternative)) {
                    $satisfied = true;
                    break;
                }
            }

            // Row locking expressed through output/hints: SQL Server has no FOR UPDATE.
            if ($capability === Capability::OUTPUT_CLAUSE && $dialect instanceof AbstractDialect && $dialect->supportsOutputClause()) {
                $satisfied = true;
            }

            if (! $satisfied) {
                $missing[] = $capability;
            }
        }

        if ($missing === []) {
            return;
        }

        throw new UnsupportedFeatureException(
            feature: implode(', ', $missing),
            engine: $dialect->vendor()->value,
            engineVersion: $dialect->version()->raw,
            reason: 'Capability not supported by the negotiated dialect version',
            alternatives: $this->alternativesFor($missing, $dialect),
        );
    }

    /**
     * @param  list<string>  $missing
     * @return list<string>
     */
    private function alternativesFor(array $missing, DialectInterface $dialect): array
    {
        $alternatives = [];

        foreach ($missing as $capability) {
            $alternatives = array_merge($alternatives, match ($capability) {
                Capability::RETURNING => [
                    'Follow-up SELECT using lastInsertId() when available',
                    'Wrap the operation in a transaction and re-read the affected rows',
                ],
                Capability::UPSERT => ['Use a transaction with SELECT ... FOR UPDATE then INSERT/UPDATE'],
                Capability::SKIP_LOCKED => ['Use a queue/worker pattern or optimistic locking'],
                Capability::JSON, Capability::JSON_CONTAINS => ['Store the data in normalized columns and index them'],
                Capability::LATERAL => ['Rewrite as a correlated subquery or a two-step query'],
                Capability::FULL_JOIN => ['Rewrite as LEFT JOIN + RIGHT JOIN combined with UNION'],
                default => [],
            });
        }

        return array_values(array_unique($alternatives));
    }
}
