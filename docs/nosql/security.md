# Security — OrmUniversal\NoSql

## Threat model

Filters arrive as operator documents (`['field' => ['$op' => value]]`). If they are
built from unvalidated user input, an attacker can inject unexpected operators (NoSQL
injection).

## Rules

1. **Build filters with `Filter`**, not with arbitrary request arrays.
   ```php
   Filter::make()->eq('email', $email)->gte('age', $minAge);
   ```
2. **Validate fields and values** against an allowlist before passing them to the filter.
   `Filter::regex()` does not sanitize the pattern: a malicious pattern can cause ReDoS.
3. **Do not use the full user payload** as a filter or as an update
   document. `updateMany()` forwards the array as-is to the driver.
4. **Collection identifiers** are controlled by the application; do not derive them from
   untrusted input (they affect Redis/Mongo keys).
5. **Credentials per environment**, never in code:
   - `MongoDbDriver`: URI with user/password via environment variables.
   - `RedisDriver`: `password` and `database` via configuration.
   The driver does not encrypt transport: use TLS at the server/network level if you need it.
6. **Redis**: the prefix (`prefix`) prevents collisions between applications in the same database;
   it is not a security boundary. Keys are not encrypted at rest unless the
   infrastructure does so.

## Error handling

- `DriverException`: unknown driver, missing extension, or failed connection.
- `UnsupportedFeatureException`: unsupported capability. Do not catch it to simulate the
  result in SQL; use the indicated alternatives.
- Do not include credentials in logs. Driver messages do not expose them, but the connection
  URI can appear in traces from the `mongodb/mongodb` library.

## Scope

Failures of MongoDB, Redis, or their extensions are not handled in this package.
Report vulnerabilities in the delivered driver through the private channel in `SECURITY.md`.
