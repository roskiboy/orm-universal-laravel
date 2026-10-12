<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use ReflectionClass;
use ReflectionMethod;

/**
 * Compares the public method surface of Laravel database classes against the
 * UDE equivalents and classifies every Laravel method as:
 *
 * - parity: same name exists in a target class.
 * - alias: a documented equivalent with a different name exists.
 * - na: not applicable by architecture (internals, magic, interactive helpers).
 * - defer: real feature pending, with a documented reason.
 * - gap: real feature not covered yet.
 *
 * The classification data is the single source of truth for the parity
 * document (`docs/ude/PARITY_LARAVEL13.md`).
 *
 * @api public
 */
final class ParityAnalyzer
{
    public const PARITY = 'parity';

    public const ALIAS = 'alias';

    public const NA = 'na';

    public const DEFER = 'defer';

    public const GAP = 'gap';

    /**
     * Method aliases per source class: Laravel method => UDE method.
     *
     * @var array<string, array<string, string>>
     */
    private const ALIASES = [
        'Illuminate\Database\Query\Builder' => [
            'from' => 'fromTable',
            'fromSub' => 'fromSubquery',
            'lockForUpdate' => 'lock',
            'sharedLock' => 'lock',
            'average' => 'avg',
            'selectExpression' => 'select',
            'whereNested' => 'where',
            'havingNested' => 'having',
            'toSql' => 'sql',
            'toRawSql' => 'sql',
        ],
    ];

    /**
     * Internal/infrastructure methods that do not require parity.
     *
     * @var list<string>
     */
    private const INTERNAL = [
        '__call',
        'addBinding',
        'addNestedHavingQuery',
        'addNestedWhereQuery',
        'addWhereExistsQuery',
        'afterQuery',
        'aggregate',
        'applyAfterQueryCallbacks',
        'applyBeforeQueryCallbacks',
        'beforeQuery',
        'castBinding',
        'cleanBindings',
        'clone',
        'cloneWithout',
        'cloneWithoutBindings',
        'fetchUsing',
        'forNestedWhere',
        'getBindings',
        'getColumns',
        'getConnection',
        'getCountForPagination',
        'getGrammar',
        'getLimit',
        'getOffset',
        'getProcessor',
        'getRawBindings',
        'implode',
        'mergeBindings',
        'mergeWheres',
        'newQuery',
        'numericAggregate',
        'prepareValueAndOperator',
        'rawValue',
        'setBindings',
        'whereNested',
    ];

    /**
     * Reasons for methods that are not applicable per source class.
     *
     * @var array<string, array<string, string>>
     */
    private const NOT_APPLICABLE = [
        'Illuminate\Database\Query\Builder' => [
            'dd' => 'Helper interactivo de depuración; UDE expone `sql()` para inspección.',
            'dump' => 'Helper interactivo de depuración; UDE expone `sql()` para inspección.',
            'ddRawSql' => 'Helper interactivo de depuración; usar `ude:query:inspect`.',
            'dumpRawSql' => 'Helper interactivo de depuración; usar `ude:query:inspect`.',
            'dynamicWhere' => 'Magia `where{Columna}` prohibida por ADR-009 (resolución explícita).',
            'useWritePdo' => 'UDE elige conexión por nombre (`connections()`), no por lectura/escritura.',
        ],
        'Illuminate\Database\Eloquent\Builder' => [
            'applyScopes' => 'Semántica de modelo: el modo híbrido conserva Eloquent.',
            'except' => 'Selección de modelo/columnas de Eloquent; usar `select()`/`get()`.',
            'eagerLoadRelations' => 'Carga de relaciones Eloquent; UDE usa `usingRelations()`/`with()`.',
            'forceCreate' => 'Altas de modelo Eloquent; usar `create()` o el modelo.',
            'forceCreateQuietly' => 'Altas de modelo Eloquent; usar `create()` o el modelo.',
            'forceDelete' => 'Borrado de modelo Eloquent (soft deletes).',
            'getEagerLoads' => 'Metadatos de eager loading de Eloquent.',
            'getMacro' => 'Macros de Eloquent (no aplica al AST).',
            'getModel' => 'Acceso al modelo Eloquent.',
            'getModels' => 'Hidratación de modelos Eloquent; usar `models()`/`entity()`.',
            'getRelation' => 'Relaciones de modelo Eloquent.',
            'hasMacro' => 'Macros de Eloquent (no aplica al AST).',
            'hasNamedScope' => 'Scopes de modelo Eloquent.',
            'hydrate' => 'Hidratación de modelo Eloquent; usar `models()`/`entity()`.',
            'make' => 'Fábrica de modelos Eloquent.',
            'modelKeys' => 'Claves de modelo Eloquent.',
            'newModelInstance' => 'Fábrica de modelos Eloquent.',
            'onClone' => 'Ciclo de vida de Eloquent.',
            'onDelete' => 'Ciclo de vida de Eloquent.',
            'qualifyColumn' => 'Calificación de columnas de modelo; UDE califica por tabla explícita.',
            'qualifyColumns' => 'Calificación de columnas de modelo; UDE califica por tabla explícita.',
            'removedScopes' => 'Scopes de modelo Eloquent.',
            'scopes' => 'Scopes de modelo Eloquent.',
            'setEagerLoads' => 'Metadatos de eager loading de Eloquent.',
            'setModel' => 'Acceso al modelo Eloquent.',
            'setQuery' => 'Builder interno de Eloquent.',
            'toBase' => 'Conversión al Query\Builder de Laravel.',
            'touch' => 'Timestamps de modelo Eloquent.',
            'withAttributes' => 'Atributos de modelo Eloquent.',
            'withCasts' => 'Casts de modelo Eloquent; UDE usa `castWith()`.',
            'withGlobalScope' => 'Scopes globales de Eloquent.',
            'withOnly' => 'Atributos de modelo Eloquent.',
            'without' => 'Atributos de modelo Eloquent.',
            'withoutEagerLoad' => 'Eager loading de Eloquent.',
            'withoutEagerLoads' => 'Eager loading de Eloquent.',
            'withoutGlobalScope' => 'Scopes globales de Eloquent.',
            'withoutGlobalScopes' => 'Scopes globales de Eloquent.',
            'withoutGlobalScopesExcept' => 'Scopes globales de Eloquent.',
            'withSavepointIfNeeded' => 'Transacciones anidadas de Eloquent; el ejecutor UDE maneja savepoints.',
            '__clone' => 'Interno de Eloquent.',
        ],
        'Illuminate\Database\Schema\Blueprint' => [
            'bigIncrements' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'bigInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'binary' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'boolean' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'char' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'date' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'dateTime' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'decimal' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'double' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'enum' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'float' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'foreign' => 'Definición de FKs vía `ForeignKeyDefinition` (VOs).',
            'foreignId' => 'Definición de columnas/FKs vía VOs.',
            'foreignUlid' => 'Definición de columnas/FKs vía VOs.',
            'foreignUuid' => 'Definición de columnas/FKs vía VOs.',
            'fullText' => 'Definición de índices vía `IndexDefinition` (VOs).',
            'id' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'increments' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'index' => 'Definición de índices vía `IndexDefinition` (VOs).',
            'integer' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'ipAddress' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'json' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'jsonb' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'longText' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'macAddress' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'mediumIncrements' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'mediumInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'mediumText' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'morphs' => 'Definición de columnas vía VOs.',
            'nullableMorphs' => 'Definición de columnas vía VOs.',
            'nullableTimestamps' => 'Definición de columnas vía VOs.',
            'nullableUlidMorphs' => 'Definición de columnas vía VOs.',
            'nullableUuidMorphs' => 'Definición de columnas vía VOs.',
            'primary' => 'Definición de índices/columnas vía VOs.',
            'rememberToken' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'renameColumn' => 'Operación de tabla vía `SchemaOperation`.',
            'renameIndex' => 'Operación de tabla vía `SchemaOperation`.',
            'set' => 'Alteración MySQL de columnas vía `SchemaOperation`.',
            'smallIncrements' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'smallInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'softDeletes' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'softDeletesTz' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'spatialIndex' => 'Definición de índices vía `IndexDefinition` (VOs).',
            'string' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'text' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'time' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'timestamp' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'timestamps' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'timestampsTz' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'tinyIncrements' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'tinyInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'tinyText' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'ulid' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'ulidMorphs' => 'Definición de columnas vía VOs.',
            'unique' => 'Definición de índices vía `IndexDefinition` (VOs).',
            'uniqueId' => 'Definición de columnas vía VOs.',
            'unsignedBigInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'unsignedDecimal' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'unsignedInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'unsignedMediumInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'unsignedSmallInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'unsignedTinyInteger' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'uuid' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
            'uuidMorphs' => 'Definición de columnas vía VOs.',
            'year' => 'Definición de columnas vía `ColumnDefinition` (VOs), no API fluida.',
        ],
    ];

    /**
     * Real features deferred with an explicit reason and alternative.
     *
     * @var array<string, array<string, string>>
     */
    private const DEFERRED = [
        'Illuminate\Database\Query\Builder' => [
            'whereFullText' => 'Sintaxis por dialecto (MATCH AGAINST / tsquery / CONTAINS): planificado con capabilities; alternativa actual: `whereRaw()`.',
            'orWhereFullText' => 'Sintaxis por dialecto; alternativa actual: `whereRaw()` con OR.',
            'fromRaw' => 'FROM con SQL crudo: alternativa tipada actual `fromSubquery()`; planificado como fuente raw validada por dialecto.',
            'joinLateral' => 'LATERAL/CROSS APPLY por dialecto: planificado; alternativa actual: `joinSub()`.',
            'leftJoinLateral' => 'LATERAL/OUTER APPLY por dialecto: planificado; alternativa actual: `leftJoinSub()`.',
            'straightJoin' => 'Hint exclusivo de MySQL; alternativa actual: `raw()` en la tabla.',
            'straightJoinSub' => 'Hint exclusivo de MySQL; alternativa actual: `joinSub()` + `raw()`.',
            'straightJoinWhere' => 'Hint exclusivo de MySQL; alternativa actual: `joinWhere()` + `raw()`.',
            'useIndex' => 'Hint de índice exclusivo de MySQL/MariaDB; planificado en el AST; alternativa actual: `raw()` en la tabla.',
            'forceIndex' => 'Hint de índice exclusivo de MySQL/MariaDB; planificado en el AST; alternativa actual: `raw()` en la tabla.',
            'ignoreIndex' => 'Hint de índice exclusivo de MySQL/MariaDB; planificado en el AST; alternativa actual: `raw()` en la tabla.',
            'groupLimit' => 'Limita filas por grupo con ROW_NUMBER: planificado sobre WindowExpression; alternativa actual: window functions manuales o `whereInSub()`.',
            'timeout' => 'Timeout por consulta sin soporte uniforme en PDO; alternativa actual: `statement_timeout` del servidor y RetryPolicy.',
            'updateFrom' => 'UPDATE ... FROM solo en PostgreSQL/SQL Server; requiere capability dedicada; alternativa actual: subconsulta en el SET.',
            'inOrderOf' => 'Orden por lista de valores: planificado con CASE/FIELD por dialecto; alternativa actual: `orderByRaw()` con bindings.',
            'insertOrIgnoreReturning' => 'Combinación INSERT IGNORE + RETURNING divergente por motor; alternativa actual: `insertOrIgnore()` + `returning()` en el AST.',
            'whereJsonLength' => 'Requiere JSON_LENGTH/jsonb_array_length/JSON_ARRAY_LENGTH por dialecto: planificado; alternativa actual: `whereRaw()`.',
            'whereJsonOverlaps' => 'Requiere JSON_OVERLAPS/operador `?|` por dialecto: planificado; alternativa actual: `whereRaw()`.',
            'orWhereJsonLength' => 'Requiere JSON_LENGTH/jsonb_array_length/JSON_ARRAY_LENGTH por dialecto: planificado; alternativa actual: `whereRaw()`.',
            'orWhereJsonOverlaps' => 'Requiere JSON_OVERLAPS/operador `?|` por dialecto: planificado; alternativa actual: `whereRaw()`.',
            'whereJsonDoesntOverlap' => 'Requiere JSON_OVERLAPS/operador `?|` por dialecto: planificado; alternativa actual: `whereRaw()`.',
            'orWhereJsonDoesntOverlap' => 'Requiere JSON_OVERLAPS/operador `?|` por dialecto: planificado; alternativa actual: `whereRaw()`.',
            'macro' => 'Registro de macros nativo (ADR-001 prohíbe Illuminate\\Support): planificado como extensión del PluginRegistry.',
            'hasMacro' => 'Registro de macros nativo: planificado como extensión del PluginRegistry.',
            'flushMacros' => 'Registro de macros nativo: planificado como extensión del PluginRegistry.',
            'macroCall' => 'Registro de macros nativo: planificado como extensión del PluginRegistry.',
            'mixin' => 'Registro de macros nativo: planificado como extensión del PluginRegistry.',
            'whereVectorSimilarTo' => 'Búsqueda vectorial: requiere MariaDB 11.7+/pgvector; el stack corre MariaDB 11.6. API reservada.',
            'whereVectorDistanceLessThan' => 'Búsqueda vectorial: requiere MariaDB 11.7+/pgvector; el stack corre MariaDB 11.6. API reservada.',
            'orWhereVectorDistanceLessThan' => 'Búsqueda vectorial: requiere MariaDB 11.7+/pgvector; el stack corre MariaDB 11.6. API reservada.',
            'selectVectorDistance' => 'Búsqueda vectorial: requiere MariaDB 11.7+/pgvector; el stack corre MariaDB 11.6. API reservada.',
            'orderByVectorDistance' => 'Búsqueda vectorial: requiere MariaDB 11.7+/pgvector; el stack corre MariaDB 11.6. API reservada.',
        ],
    ];

    /**
     * Architectural default per source class: applied after parity, aliases,
     * internals and explicit rules.
     *
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'Illuminate\Database\Eloquent\Builder' => 'Sugar de modelo Eloquent; el modo híbrido conserva Eloquent intacto (ADR-006).',
        'Illuminate\Database\Schema\Blueprint' => 'Definición declarativa vía ColumnDefinition/IndexDefinition/ForeignKeyDefinition + SchemaOperation (ADR-007); la API fluida de Blueprint no se replica.',
    ];

    /**
     * @param  list<class-string>  $targets
     * @return array{source: string, targets: list<string>, counts: array<string, int>, parity: list<string>, alias: array<string, string>, na: array<string, string>, defer: array<string, string>, gap: list<string>}
     */
    public function compare(string $source, array $targets): array
    {
        $sourceMethods = $this->publicMethods($source);
        $targetMethods = [];

        foreach ($targets as $target) {
            if (! class_exists($target)) {
                continue;
            }

            $targetMethods[$target] = $this->publicMethods($target);
        }

        $flatTarget = array_merge(...array_values($targetMethods ?: [[]]));

        $counts = [self::PARITY => 0, self::ALIAS => 0, self::NA => 0, self::DEFER => 0, self::GAP => 0];

        /** @var list<string> $parity */
        $parity = [];
        /** @var list<string> $gap */
        $gap = [];
        /** @var array<string, string> $alias */
        $alias = [];
        /** @var array<string, string> $na */
        $na = [];
        /** @var array<string, string> $defer */
        $defer = [];

        foreach ($sourceMethods as $method) {
            $classification = $this->classify($source, $method, $flatTarget);

            $counts[$classification['type']]++;

            if ($classification['type'] === self::PARITY) {
                $parity[] = $method;
            } elseif ($classification['type'] === self::GAP) {
                $gap[] = $method;
            } elseif ($classification['type'] === self::ALIAS) {
                $alias[$method] = $classification['reason'];
            } elseif ($classification['type'] === self::NA) {
                $na[$method] = $classification['reason'];
            } else {
                $defer[$method] = $classification['reason'];
            }
        }

        sort($parity);
        sort($gap);
        ksort($alias);
        ksort($na);
        ksort($defer);

        return [
            'source' => $source,
            'targets' => array_values($targets),
            'counts' => $counts,
            'parity' => $parity,
            'alias' => $alias,
            'na' => $na,
            'defer' => $defer,
            'gap' => $gap,
        ];
    }

    /**
     * @param  list<string>  $targetMethods
     * @return array{type: string, reason: string}
     */
    private function classify(string $source, string $method, array $targetMethods): array
    {
        if (in_array($method, $targetMethods, true)) {
            return ['type' => self::PARITY, 'reason' => ''];
        }

        $alias = self::ALIASES[$source][$method] ?? self::ALIASES['Illuminate\Database\Query\Builder'][$method] ?? null;

        if ($alias !== null && in_array($alias, $targetMethods, true)) {
            return ['type' => self::ALIAS, 'reason' => $alias];
        }

        if (in_array($method, self::INTERNAL, true) || str_starts_with($method, '__')) {
            return ['type' => self::NA, 'reason' => 'Interno de Laravel; el AST UDE no lo necesita.'];
        }

        $na = self::NOT_APPLICABLE[$source][$method] ?? null;

        if ($na !== null) {
            return ['type' => self::NA, 'reason' => $na];
        }

        $defer = self::DEFERRED[$source][$method] ?? null;

        if ($defer !== null) {
            return ['type' => self::DEFER, 'reason' => $defer];
        }

        $default = self::DEFAULTS[$source] ?? null;

        if ($default !== null) {
            return ['type' => self::NA, 'reason' => $default];
        }

        return ['type' => self::GAP, 'reason' => 'Pendiente de implementar en UDE.'];
    }

    /**
     * @return list<string>
     */
    private function publicMethods(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__construct') {
                continue;
            }

            $methods[] = $method->getName();
        }

        sort($methods);

        return $methods;
    }
}
