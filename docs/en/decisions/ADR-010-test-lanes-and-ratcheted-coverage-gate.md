---
title: ADR-010 Test lanes and a ratcheted coverage gate
lang: en-US
---

# ADR-010: Test lanes and a ratcheted coverage gate

## Status
Accepted

## Date
2026-11-03 (recorded 2026-11-03)

## Context

The suite ran green with no coverage driver at all ("No code coverage driver available"), so
two specified behaviors were only on paper — the 50 MB lazy-IDAT memory guarantee and the
`ChunkType` property flags — and the dev container that produced the numbers was tribal
knowledge ("rebuild — see project maintainer"). Third-party fixtures are banned by repo rules
(licensing and personal data), so real-world evidence could not be committed either.

## Decision

Split the work into three lanes and make coverage a measured, gated number:

- **unit** (`composer test`): the default suite, excluding the `performance` group via
  `phpunit.xml`. Fast, deterministic, no graphics stack.
- **performance** (`composer test:performance`): `#[Group('performance')]`, run with
  `php -d memory_limit=512M` because the 50 MB fixture alone exceeds the 128 MB default.
- **integration** (`composer test:integration`): `tests/Integration`, driven by
  `PNGSUITE_DIR`; skips loudly when unset and never downloads the corpus in CI.
- **Coverage**: `composer coverage` (clover + text) feeds `tools/check-coverage.php`, a
  self-owned ~50-line script that compares the clover statement rate against a committed
  `BASELINE = 89.0` and exits non-zero below it. The baseline is a ratchet: it may only rise.
- **Environment**: the `pngmeta-dev` image is built from the committed `Dockerfile`
  (`php:8.3-cli` + `exif` + pcov; GD absent by design), so any machine can reproduce the
  number. CI keeps the 8.3/8.4/8.5 matrix with `coverage: none` and adds one `coverage` job
  (8.3 + pcov) that measures, gates, runs the performance lane and uploads `coverage.xml`.

## Alternatives Considered

- **A third-party coverage-threshold dev dependency.** Rejected: the check is ~50 lines and
  the package stays dependency-free in `tools/`.
- **Always-on `<coverage>` config in `phpunit.xml`.** Rejected in practice: its presence makes
  PHPUnit collect coverage on *every* run, so `composer test` would require pcov. The config
  lives in `<source>` + the flags on the `composer coverage` script instead.
- **A hosted coverage service (Codecov, etc.).** Rejected as out of scope: the artifact plus the
  gate is the mechanism; a dashboard changes nothing about enforcement.
- **CI downloading PngSuite.** Rejected: network flakiness would poison the gate; the corpus
  runs on demand locally or pre-release.
- **Per-file golden expectations for the corpus.** Rejected: corpus-agnostic assertions (typed
  document or typed exception, `toArray()` is an array) keep the maintenance cost at zero;
  golden mapping is a separate, larger change.
- **xdebug instead of pcov.** Rejected: pcov is purpose-built for coverage and much lighter.

## Consequences

- Coverage cannot silently regress; every group of this suite raised the floor (84 → 89) by
  adding `#[CoversClass]` attribution, which is also why the attribution convention is written
  into AGENTS.md §8.
- `composer test` stays the fast inner loop, and the heavy or environment-dependent work is
  explicit — but nothing here is optional at release time: AGENTS.md §6 lists the full lane run
  as the pre-release checklist.
- Memory-bound behavior is now a tested claim rather than a spec sentence.
- Randomized coverage (`tests/Unit/RandomizedCorpusTest.php`) uses fixed seeds through
  `\Random\Randomizer`, so it is deterministic per PHP version and re-run safe.
- The gate measures attributed lines only: a test class without `#[CoversClass]` contributes
  nothing, which under-counts until noticed — hence the explicit convention.

## Sources

`testing-hardening` design D1–D8; AGENTS.md §6 and §8; `Dockerfile`; `phpunit.xml`;
`composer.json` scripts; `tools/check-coverage.php`; `.github/workflows/main.yml`.
