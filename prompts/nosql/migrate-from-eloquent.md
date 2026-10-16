# Prompt: migrate Eloquent data/queries to NoSQL without breaking Eloquent

## Context

In a Laravel app there is semi-structured data (payloads, metadata, event logs) that today
lives in JSON columns or EAV tables. The goal is to move it to documents with
`OrmUniversal\NoSql`, keeping Eloquent as the main interface during the transition.

## Goal

Ask an agent to:

1. Identify the candidate data set (large JSON, per-document reads, no joins).
2. Model the document with a stable `_id` (string/int), not dependent on autoincrement.
3. Implement temporary **dual-write**: the write still goes to Eloquent and also writes the
   document via `DocumentStore` (or the reverse) within the same business transaction.
4. Migrate reads first to UDE with a feature flag, comparing results against Eloquent.
5. Once parity is verified, remove the JSON column/EAV table.
6. Snapshots/backfill with idempotent jobs (repeatable without duplicating: deterministic `_id` and
   `updateMany` with `$set`).

## Rules

- Eloquent still leads: do not change models or events during the dual-write phase.
- Choose the driver by real capabilities:
  - documents + aggregation → MongoDB;
  - key-value/TTL → Redis (no aggregation);
  - tests/unit → InMemory.
- Do not promise NoSQL transactions if the driver does not expose them in the contract.
- Document the rollback: delete documents by `_id` and read again from the original table.

## Deliverables

- Idempotent backfill job.
- Feature flag to switch Eloquent/NoSQL reads.
- Parity test (same logical result) and idempotency test.
- Rollback runbook.

## Verification

```bash
php artisan test
php artisan ude:compatibility --json
```

## Boundaries

Real Mongo/Redis require a server and extension; tests must skip with a notice if
`available()` is `false`. The Redis driver filters in PHP (not on the server) and does not aggregate.
