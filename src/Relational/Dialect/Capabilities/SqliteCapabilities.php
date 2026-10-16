<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect\Capabilities;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * SQLite capability matrix aligned with sqlite.org/changes.html. Support baseline
 * is 3.35 (RETURNING); latest documentada 3.54.
 *
 * Documented cutoffs:
 * - 3.8.3: CTE (recursive from the start)
 * - 3.24: UPSERT (ON CONFLICT DO UPDATE)
 * - 3.25: window functions
 * - 3.30: aggregate FILTER, partial index improvements
 * - 3.31: generated columns
 * - 3.35: RETURNING
 * - 3.38: JSON -> and ->> operators, JSON built into the core
 * - 3.45: JSONB internal format
 *
 * SQLite is single writer: no row locking, no LATERAL, no procedures, no enum
 * types. Limits depend on compile options; PHP bundles a 32766 variable limit.
 *
 * @api public
 */
final class SqliteCapabilities
{
    public static function for(DialectVersion $version): CapabilitySet
    {
        $caps = CapabilitySet::of([
            Capability::CTE,
            Capability::RECURSIVE_CTE,
            Capability::UPSERT,
            Capability::UPSERT_MULTI,
            Capability::GENERATED_COLUMNS,
            Capability::GENERATED_STORED,
            Capability::PARTIAL_INDEX,
            Capability::FUNCTIONAL_INDEX,
            Capability::CHECK_CONSTRAINTS,
            Capability::DEFERRABLE_CONSTRAINTS,
            Capability::DDL_TRANSACTIONAL,
            Capability::SAVEPOINT,
            Capability::NESTED_TRANSACTIONS,
            Capability::STREAMING_CURSOR,
            Capability::LOCK,
            Capability::INSERT_IGNORE,
            Capability::INSERT_REPLACE,
            Capability::GROUP_CONCAT,
            Capability::GROUP_CONCAT_DISTINCT,
            Capability::FULLTEXT,
            Capability::EXPLAIN,
            Capability::EXISTS_PROJECTION,
            Capability::FILTER_CLAUSE,
            Capability::IS_DISTINCT_FROM,
            Capability::ANY_VALUE,
            Capability::DATE_PART,
        ]);

        if ($version->atLeast(3, 25)) {
            $caps = $caps->with(Capability::WINDOW_FUNCTIONS);
        }

        if ($version->atLeast(3, 24)) {
            $caps = $caps->with(Capability::JSON);
        }

        if ($version->atLeast(3, 38)) {
            $caps = $caps->with(Capability::JSON_OPERATORS);
        }

        if ($version->atLeast(3, 35)) {
            $caps = $caps->with(Capability::RETURNING);
        }

        if ($version->atLeast(3, 32)) {
            $caps = $caps->with(Capability::MAX_PLACEHOLDERS, 32766);
        } else {
            $caps = $caps->with(Capability::MAX_PLACEHOLDERS, 999);
        }

        return $caps->with(Capability::MAX_IDENTIFIER_LENGTH, 255);
    }
}
