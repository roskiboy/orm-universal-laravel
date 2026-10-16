# Security — OrmUniversal\Contracts

`contracts` opens no connections and executes no SQL, but it sets the security rules that
engines must respect.

## Security model

1. **Values always as bindings.** No contract accepts values interpolated into SQL.
   `SqlFragment::value()` produces `'?'` with the attached binding.
2. **Explicit raw SQL.** `SqlFragment` forces classifying the fragment with `RawType`:
   - `Trusted`: SQL controlled by the developer.
   - `Parameterized`: SQL with separate bindings.
   - `Identifier`: identifier that must be quoted and validated.
   - `Value`: value with a mandatory binding.
3. **Capabilities negotiated, never assumed.** `CapabilitySet::requireAll()` throws
   `UnsupportedFeatureException` with engine, version, feature, and alternatives instead of
   degrading silently.
4. **Immutable context.** `ContextInterface::with()` / `without()` return copies; there is
   no mutable global state in the contracts.

## Reporting scope

The following are considered `contracts` vulnerabilities:

- Evasion of the `SqlFragment` classification or absence of binding validation.
- `CapabilitySet` that declares support without a verifiable cutoff.
- Leaks of sensitive information in `UnsupportedFeatureException` messages.

Failures of the database engine, the PDO driver, or third-party extensions
(ext-mongodb, ext-redis) are not vulnerabilities of this package.

## Sensitive data

- `ConnectionConfig` carries credentials; it must not be serialized or logged.
  Consumers are responsible for secret custody.
- `QueryProfileEvent` may contain SQL; review it before exposing it.
- `PersistenceException::context()` may include parameters: filter before logging.

## Error handling

Catch platform exceptions through `PersistenceException` and use `context()` for
telemetry. Do not expose `getMessage()` with user data directly to the client.

## Reporting channel

See `SECURITY.md` at the package root for the private channel and the supported
versions. Do not open public issues for vulnerabilities.
