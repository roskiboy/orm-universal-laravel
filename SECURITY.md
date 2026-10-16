# Security Policy — ORM Universal Laravel

## Supported versions

| Version | Supported | Notes |
|---|---|---|
| `0.7.x` | Yes | Unified package; SQLite and golden tests verified |
| `0.1.x`–`0.6.x` | No | Pre-unification packages (`orm-universal/*`), superseded by `0.7.0` |

SQL Server and Oracle are implemented at the dialect/DDL level, but their real integration
is pending on infrastructure: they are not declared stable. See `CHANGELOG.md`.

## Private reporting channel

Do not open public issues for vulnerabilities. Use GitHub's private reporting:

- https://github.com/roskiboy/orm-universal-laravel/security/advisories/new
- Alternative: contact the In-Tech maintainers through the repository.

Include version, affected layer/engine, description, minimal PoC, impact and mitigation.
Indicative acknowledgement: 72 business hours.

## Scope

In scope:

- SQL injection through unquoted/unvalidated identifiers (`TableIdentifier`,
  `ColumnIdentifier`, `ORDER BY` suffixes).
- Leaking values into compiled SQL instead of bindings.
- Incorrect classification of raw SQL (`SqlFragment`/`RawType`) that allows executing
  uncontrolled SQL.
- A `DestructiveStatementPolicy` that allows a destructive operation without confirmation
  when the policy requires it.
- Exposure of credentials (`ConnectionConfig`) in exceptions or profiling.
- NoSQL filters/pipelines evaluated against an unsupported capability without a clear
  `UnsupportedFeatureException`.

Out of scope:

- Vulnerabilities in the engines (MySQL, MariaDB, PostgreSQL, SQLite, SQL Server,
  Oracle, MongoDB, Redis) or their PDO drivers/clients.
- Raw SQL that the application explicitly marks as `trusted`.

## Delivered mitigations

- Dialect-quoted/validated identifiers; values always as bindings.
- Explicit value/identifier/raw separation in the compiler.
- `UnsupportedFeatureException` with engine/version/feature/reason/alternatives.
- Destructive statement policy with `usingPolicy()`/`confirmDestructive()`.
- NoSQL backends resolved dynamically (`class_exists`/`available()`), never silently
  degraded.

## Best practices for consumers

- Do not use `SqlFragment::trusted()` with user input.
- Keep version cutoffs up to date; do not force capabilities by hand.
- Keep PDO, engine extensions and NoSQL clients patched with their security fixes.
