# Troubleshooting — OrmUniversal\Contracts

## `composer require` fails due to PHP version

`contracts` requires PHP `^8.3`. Check with `php -v`. In the monorepo the reference
version is PHP 8.3+; CI tests 8.3, 8.4, and 8.5.

## `Class "OrmUniversal\Contracts\..." not found`

1. Run `composer dump-autoload`.
2. Verify that the package appears in `composer show roskiboy/orm-universal-laravel`.
3. If you use a path repository, confirm that the path points to `packages/contracts` and that
   you ran `composer update roskiboy/orm-universal-laravel`.

## Conflict with `psr/log` or `psr/simple-cache`

The package requires `^3.0` for both. If another dependency pins `^1.0` or `^2.0`, Composer
will block the installation; update the other dependency or isolate the package.

## Unexpected `UnsupportedFeatureException`

The exception is intentional when a capability is not declared. Read `feature`,
`engine`, `engineVersion`, and `alternatives` (or `report()`). If the capability should be
supported, check the version cutoff of the corresponding dialect in `relational`; do not
modify `CapabilitySet` by hand to silence it.

## PHPStan reports classes not found in `relational`/`nosql`

That analysis belongs to each package. `contracts` only analyzes its `src/` at level 6.

## API changes break consumers

The policy is SemVer: an incompatible change requires `BREAKING CHANGE` in a
Conventional Commits commit and a new minor/major version as appropriate. See the package's
`DOCUMENTATION.md` and `RELEASES.md`.

## `SqlFragment` throws `InvalidArgumentException`

A `RawType::Value` fragment without bindings is invalid by design. Use
`SqlFragment::value($v)` or `SqlFragment::parameterized('...', [$v])`.

## The suite fails due to output in tests

`beStrictAboutOutputDuringTests` is active: remove `echo`/`var_dump` from tests and use
assertions.
