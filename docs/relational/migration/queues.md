# Migration: queues and jobs

Queue workers are long-lived processes with retries. Two risks:
state leaked between jobs and non-idempotent work.

## Lifecycle per job

```php
use OrmUniversal\Relational\Context\Context;
use OrmUniversal\Relational\Context\ContextScope;

$scope = new ContextScope;

$scope->run(
    Context::of(['tenant' => $tenant, 'job_id' => $jobId]),
    function (Context $context): void {
        // job work
    },
);

// mandatory cleanup, ideally in a finally block
$scope->flush();
```

In a real worker wrap the cleanup in `finally` and call
`ConnectionManager::purge()` if the process will remain idle for a long time.

## Transaction per unit of work

```php
$tx = $executor->transaction();
$tx->begin();

try {
    \OrmUniversal\Relational\Builder\QueryBuilder::table($executor, 'orders')
        ->where('id', $orderId)
        ->update(['status' => 'processing']);

    $tx->commit();
} catch (\Throwable $e) {
    if ($tx->isActive()) {
        $tx->rollBack();
    }
    throw $e; // let the queue retry
}
```

`savepoint()`/`rollbackTo()` allow isolating sub-steps without aborting the whole
job.

## Idempotency

- Use `upsert($rows, $uniqueBy, $updateColumns)` for reprocessing.
- Prefer business keys (`external_id`) over autoincrementals.
- If the job writes batches, `upsertMany()` splits via `BatchPlanner` and keeps
  the placeholder budget.

## Batch reading

```php
$builder
    ->where('status', 'pending')
    ->orderBy('id')
    ->chunk(500, function (array $chunk, int $page): bool {
        // process and return false to stop
        return true;
    });
```

`chunk()` uses `cursor()` internally: it does not materialize the whole result.
For resumable ones, keyset with `where('id', '>', $lastId)`.

## Retries and states

- Do not leave transactions open when throwing the exception; `rollBack()` in the
  `catch`.
- If the job exhausts attempts, record the failure with an explicit
  `DestructiveStatementPolicy` and without accidental `truncate`.
- `PdoTransaction::state()` allows distinguishing `Failed` from `Active` when
  diagnosing.

## N+1 in jobs

Jobs usually iterate entities. Wire `N1Detector` in `Log` or
`Telemetry` mode to see relations reloaded per iteration
([Relations](../relations.md)).

## Checklist

- [ ] `ContextScope::flush()` in `finally`.
- [ ] `ConnectionManager::purge()` if the worker goes idle.
- [ ] Transaction per unit of work with guaranteed rollback.
- [ ] Idempotent writes (`upsert` or business keys).
- [ ] Batches via `chunk()`/`*Many()`.

See [Octane](octane.md), [High traffic](high-traffic.md) and
[Security](../security.md).
