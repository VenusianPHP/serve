# Agent guidelines — venusian/serve

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from Composer dist via `.gitattributes` `export-ignore`).

Before changing serve code or advising on this package:

1. Read [`.okf/index.md`](.okf/index.md) first (progressive disclosure).
2. Open only the linked concepts needed for the task.
3. Prefer `status: stable` concepts; treat `deprecated` as historical only. A concept an agent creates stays `status: draft` until a human verifies it.
4. When you learn something durable about **this package**, update the affected `.okf` concept(s) and append `.okf/log.md`. A component's `# Open items` list is re-verified against the tree whenever the rewrite lands.
5. Keep knowledge at the package-root `.okf/` only — do not nest `.okf` under `src/`.
6. Scope claims to **serve**. Voyager framework architecture belongs in `venusian/framework` OKF.

## Package rules (quick) — 0.10.0

- Composer: `venusian/serve` **0.10.0**. PHP `~8.4.27||^8.5.12`: the patched floor of each line, never a bare `^8.4`. CI enforces the same floors at runtime.
- Requires individual `venusian-voyager/*` components at `^0.10.11`, never `venusian/framework`. They come from Packagist; framework 0.10.11 publishes before serve and they deploy together.
- The `../framework` path repository lives only in the gitignored `composer.dev.json` (`COMPOSER=composer.dev.json composer update`); never commit it to `composer.json`. If one is committed, log it in `.okf/log.md` and remove it before the work ends.
- Namespace: `Mariner\` → `src/Mariner`. One component per `src/Mariner/<Component>` with its own `composer.json` (`venusian-mariner/<component>`, branch alias `0.10.x-dev`, siblings at `^0.10`); interfaces under `src/Mariner/Contracts/<Component>`.
- Dependency direction: serve depends on the framework, never the reverse. The framework never names Mariner or serve; a serve need there is a generic framework feature, described without web terms, approved per change.
- Nothing the framework already ships is duplicated here; use the `Voyager\` class.
- Container: Voyager's API (`registerSingleton`, `registerInstance`, `scoped`, `bind`, `isBound`, `isResolved`, `make`), not Laravel's. State that belongs to one request is `scoped()`: Voyager scopes it per fiber, and loop tasks share the scope of the fiber that started them.
- Releasing: `split.yml` splits each component to `venusian-mariner` on a `vX.Y.Z` tag. The split job stays commented out until those repositories exist.
- Tests: Pest (`vendor/bin/pest`). Static analysis: PHPStan level 0 (`vendor/bin/phpstan analyse --no-progress --memory-limit=2G`, `phpstan.neon`); upstream patterns are named `ignoreErrors` entries with a reason, never a baseline. CI runs both, plus `php .github/check-core-isolation.php`.
