<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect\Capabilities;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * SQL Server capability matrix for 2019, 2022 and 2025, derived from
 * learn.microsoft.com documentation.
 *
 * Documented highlights:
 * - All supported versions: OUTPUT clause, MERGE, filtered (partial) indexes,
 *   computed columns, STRING_AGG (2017+), JSON functions, indexed views,
 *   savepoints, DDL transactional per statement.
 * - 2022: JSON enhancements, GREATEST/LEAST.
 * - 2025: native json type, VECTOR type + vector indexes, JSON_CONTAINS,
 *   REGEXP functions.
 *
 * Row locking is expressed with hints (UPDLOCK) rather than FOR UPDATE, so the
 * portable lock() API reports it as unsupported with an explicit alternative.
 *
 * @api public
 */
final class SqlServerCapabilities
{
    public static function for(DialectVersion $version): CapabilitySet
    {
        $caps = CapabilitySet::of([
            Capability::OUTPUT_CLAUSE,
            Capability::MERGE,
            Capability::UPSERT,
            Capability::UPSERT_MULTI,
            Capability::GENERATED_COLUMNS,
            Capability::GENERATED_STORED,
            Capability::PARTIAL_INDEX,
            Capability::SPATIAL,
            Capability::FULLTEXT,
            Capability::SEQUENCES,
            Capability::IDENTITY,
            Capability::CHECK_CONSTRAINTS,
            Capability::DDL_TRANSACTIONAL,
            Capability::ONLINE_INDEX,
            Capability::WINDOW_FUNCTIONS,
            Capability::CTE,
            Capability::RECURSIVE_CTE,
            Capability::LATERAL,
            Capability::MATERIALIZED_VIEW,
            Capability::STRING_AGG,
            Capability::PERCENTILE,
            Capability::ROLLUP,
            Capability::GROUPING_SETS,
            Capability::SAVEPOINT,
            Capability::NESTED_TRANSACTIONS,
            Capability::STREAMING_CURSOR,
            Capability::JSON,
            Capability::TIMEZONE,
            Capability::XML,
            Capability::UUID_NATIVE,
            Capability::BINARY_UUID,
            Capability::EXPLAIN,
            Capability::DATE_PART,
        ]);

        if ($version->major() >= 2025) {
            $caps = $caps->with(Capability::VECTOR)
                ->with(Capability::REGEXP)
                ->with(Capability::JSON_TABLE);
        }

        return $caps
            ->with(Capability::MAX_PLACEHOLDERS, 2100)
            ->with(Capability::MAX_IDENTIFIER_LENGTH, 128);
    }
}
