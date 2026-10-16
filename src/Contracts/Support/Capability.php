<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Support;

/**
 * Canonical capability names. The list is intentionally extensible: CapabilitySet
 * accepts arbitrary strings, this class only names the built-in ones so first and
 * third party code agree on spelling.
 *
 * @api public
 */
final class Capability
{
    // --- Query / SQL features ---
    public const RETURNING = 'supportsReturning';

    public const OUTPUT_CLAUSE = 'supportsOutputClause';

    public const JSON = 'supportsJson';

    public const JSONB = 'supportsJsonb';

    public const JSON_TABLE = 'supportsJsonTable';

    public const JSON_CONTAINS = 'supportsJsonContains';

    public const JSON_OPERATORS = 'supportsJsonOperators';

    public const WINDOW_FUNCTIONS = 'supportsWindowFunctions';

    public const CTE = 'supportsCte';

    public const RECURSIVE_CTE = 'supportsRecursiveCte';

    public const MERGE = 'supportsMerge';

    public const UPSERT = 'supportsUpsert';

    public const UPSERT_MULTI = 'supportsUpsertMultiRow';

    public const GENERATED_COLUMNS = 'supportsGeneratedColumns';

    public const GENERATED_STORED = 'supportsStoredGeneratedColumns';

    public const PARTIAL_INDEX = 'supportsPartialIndex';

    public const FUNCTIONAL_INDEX = 'supportsFunctionalIndex';

    public const SPATIAL = 'supportsSpatial';

    public const FULLTEXT = 'supportsFullText';

    public const ARRAYS = 'supportsArrays';

    public const ENUMS = 'supportsEnums';

    public const SEQUENCES = 'supportsSequences';

    public const IDENTITY = 'supportsIdentity';

    public const NOWAIT = 'supportsNowait';

    public const LOCK = 'supportsRowLocking';

    public const SKIP_LOCKED = 'supportsSkipLocked';

    public const FULL_JOIN = 'supportsFullJoin';

    public const DATE_PART = 'supportsDatePart';

    public const IS_DISTINCT_FROM = 'supportsIsDistinctFrom';

    public const LATERAL = 'supportsLateral';

    public const MATERIALIZED_VIEW = 'supportsMaterializedView';

    public const VECTOR = 'supportsVector';

    public const CHECK_CONSTRAINTS = 'supportsCheckConstraints';

    public const DEFERRABLE_CONSTRAINTS = 'supportsDeferrableConstraints';

    public const DDL_TRANSACTIONAL = 'supportsTransactionalDdl';

    public const ONLINE_INDEX = 'supportsOnlineIndex';

    public const CONCURRENT_INDEX = 'supportsConcurrentIndex';

    public const UPDATE_LIMIT = 'supportsUpdateLimit';

    public const DELETE_LIMIT = 'supportsDeleteLimit';

    public const MULTI_TABLE_UPDATE = 'supportsMultiTableUpdate';

    public const INSERT_IGNORE = 'supportsInsertIgnore';

    public const INSERT_REPLACE = 'supportsInsertReplace';

    public const ILIKE = 'supportsIlike';

    public const REGEXP = 'supportsRegexp';

    public const SAVEPOINT = 'supportsSavepoints';

    public const NESTED_TRANSACTIONS = 'supportsNestedTransactions';

    public const STREAMING_CURSOR = 'supportsStreamingCursor';

    public const UNBUFFERED_QUERY = 'supportsUnbufferedQuery';

    public const MULTI_STATEMENT = 'supportsMultiStatement';

    public const RETURNING_BULK = 'supportsReturningBulk';

    public const GROUP_CONCAT = 'supportsGroupConcat';

    public const GROUP_CONCAT_DISTINCT = 'supportsGroupConcatDistinct';

    public const STRING_AGG = 'supportsStringAgg';

    public const STRING_AGG_DISTINCT = 'supportsStringAggDistinct';

    public const ANY_VALUE = 'supportsAnyValue';

    public const PERCENTILE = 'supportsPercentile';

    public const FILTER_CLAUSE = 'supportsAggregateFilter';

    public const ROLLUP = 'supportsRollup';

    public const GROUPING_SETS = 'supportsGroupingSets';

    public const UUID_NATIVE = 'supportsNativeUuid';

    public const ULID_NATIVE = 'supportsNativeUlid';

    public const BINARY_UUID = 'supportsBinaryUuid';

    public const TIMEZONE = 'supportsTimezoneTypes';

    public const INTERVAL = 'supportsIntervalTypes';

    public const XML = 'supportsXml';

    public const CASE_INSENSITIVE_LIKE = 'likeIsCaseInsensitive';

    public const SQL_CALC_FOUND_ROWS = 'supportsSqlCalcFoundRows';

    public const EXPLAIN = 'supportsExplain';

    public const EXISTS_PROJECTION = 'supportsExistsProjection';

    // --- Limits ---
    public const MAX_PLACEHOLDERS = 'maxPlaceholders';

    public const MAX_IDENTIFIER_LENGTH = 'maxIdentifierLength';

    // --- NoSQL capabilities ---
    public const NOSQL_TRANSACTIONS = 'supportsTransactions';

    public const NOSQL_AGGREGATION = 'supportsAggregation';

    public const NOSQL_PIPELINE = 'supportsPipeline';

    public const NOSQL_CHANGE_STREAMS = 'supportsChangeStreams';

    public const NOSQL_TTL = 'supportsTtl';

    public const NOSQL_PARTIAL_INDEXES = 'supportsPartialIndexes';

    public const NOSQL_GEO = 'supportsGeoQueries';

    public const NOSQL_VECTOR_SEARCH = 'supportsVectorSearch';

    public const NOSQL_TEXT_SEARCH = 'supportsTextSearch';

    public const NOSQL_TIME_SERIES = 'supportsTimeSeries';

    public const NOSQL_JSON_DOCUMENTS = 'supportsJsonDocuments';

    public const NOSQL_KEY_VALUE = 'supportsKeyValue';

    public const NOSQL_STREAMS = 'supportsStreams';

    public const NOSQL_PUBSUB = 'supportsPubSub';

    public const NOSQL_LUA = 'supportsLuaScripting';

    public const NOSQL_MODULES = 'supportsModules';

    public const NOSQL_CLUSTER = 'supportsCluster';

    public const NOSQL_PERSISTENCE = 'supportsPersistence';

    /**
     * @return list<string>
     */
    public static function coreSql(): array
    {
        return [
            self::RETURNING, self::JSON, self::WINDOW_FUNCTIONS, self::CTE, self::RECURSIVE_CTE,
            self::UPSERT, self::GENERATED_COLUMNS, self::PARTIAL_INDEX, self::SPATIAL,
            self::FULLTEXT, self::ARRAYS, self::ENUMS, self::SEQUENCES, self::IDENTITY,
            self::SKIP_LOCKED, self::LATERAL, self::VECTOR, self::CHECK_CONSTRAINTS,
            self::DDL_TRANSACTIONAL, self::SAVEPOINT, self::STREAMING_CURSOR, self::EXPLAIN,
        ];
    }

    /**
     * @return list<string>
     */
    public static function coreNoSql(): array
    {
        return [
            self::NOSQL_TRANSACTIONS, self::NOSQL_AGGREGATION, self::NOSQL_PIPELINE,
            self::NOSQL_CHANGE_STREAMS, self::NOSQL_TTL, self::NOSQL_PARTIAL_INDEXES,
            self::NOSQL_GEO, self::NOSQL_VECTOR_SEARCH, self::NOSQL_TEXT_SEARCH,
            self::NOSQL_JSON_DOCUMENTS, self::NOSQL_KEY_VALUE,
        ];
    }
}
