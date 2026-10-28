---
type: Practice
title: Static analysis
description: serve runs PHPStan at level 5 over src, with no baseline; every suppression is a phpstan.neon entry per cause with its reason.
tags: [phpstan, quality, ci]
resource: phpstan.neon
generated: { by: claude-code/claude-opus-5-5, at: 2026-10-28T00:00:00Z }
sources:
  - id: phpstan-neon
    resource: phpstan.neon
    title: serve's PHPStan configuration
---

# Level and scope

`vendor/bin/phpstan analyse` runs at **level 5** over `src/` and `phpstan/TraitConsumers.php`, and must report no errors.[^phpstan-neon] It was raised from level 0 one level at a time; the real defects each level found were fixed in `src`, and the commit for each level lists them.

# Rules

* **No baseline.** A finding is fixed in `src`, or suppressed by a `phpstan.neon` `ignoreErrors` entry. Each entry covers one cause, matches on identifier, message and path, and has a comment giving the reason. No inline `@phpstan-ignore` except the two `notify()` calls, which name their reason. A blanket ignore (identifier alone, or a whole directory without a message) is not allowed.
* **Unmatched entries fail the run** (PHPStan's default), so an entry goes once its cause is fixed.
* **`treatPhpDocTypesAsCertain: false`.** Docblock types aren't enforced at runtime, so checks they would make look redundant stay: they guard values crossing trust boundaries.
* **Traits only apps use are analysed** through `phpstan/TraitConsumers.php`, which is never loaded; PHPStan skips a trait no class uses.
* **Suppressed causes, by kind:** framework typing (also reported to the framework's backlog, never patched from serve), Laravel 13.37 parity where Mariner's contracts mirror Laravel's, and third-party typing (Symfony Mailer/Mime, the Resend SDK).

# Raising the level

Raise `level` by one, fix what is real in `src`, add an entry with a reason for each remaining cause, and commit that level on its own with its findings listed. Run the full Pest suite and the core isolation check before committing.

[^phpstan-neon]: `phpstan.neon` at the repository root.
