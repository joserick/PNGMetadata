---
title: ADR-009 v1 compatibility removed outright
lang: en-US
---

# ADR-009: v1 compatibility removed outright

## Status
Accepted

## Date
2026-11-02 (recorded 2026-11-03)

## Context

The first v2 iteration kept `PNGMetadata` (a ~240-line `ArrayObject` facade) and
`LegacyArrayFormatter` on top of the typed core purely as a compatibility surface, with
golden tests freezing v1's byte-for-byte output — including its quirks, such as the IHDR
group silently omitting `Interlace`. v2.0.0 was still unreleased, `1.x` still existed for
legacy PHP (7.4/8.0), and the facade's only consumer was migration comfort.

## Decision

Delete the facade and the formatter in 2.0.0, with no deprecation release. What exits:
`ArrayObject` inheritance, `extract()` (null-on-failure), the legacy grouped `toArray()`
shape, colon-path `get('a:b:c')`, `__toString()` two-column rendering, `getThumbnail()`
(`\GdImage`), `isPNG()`, `getType()` and the numeric codes 101/102/103. What replaces them is
either the typed API (`PngMetadataReader::create()->read()` → `MetadataDocument`) or, for
presentation, a documented recipe in the README migration table. `ext-gd` disappears from the
package entirely (ADR-005) and the typed taxonomy becomes the only error contract (ADR-006).
The `1.x` branch stays the home of the old API, receiving security fixes only.

## Alternatives Considered

- **Ship v2 with a `@deprecated` facade, remove it in v3.** Rejected: it would spend a whole
  release repeating "the next major breaks you anyway", keep the parity tests and the quirk
  lock alive for one more major, and v2 never advertised the facade as its API direction.
- **Port `get('a:b:c')` as a generic path getter on `MetadataDocument`.** Rejected: it
  re-introduces stringly-typed access to a typed model — the exact reason v1 was hard to
  consume safely.
- **Keep a rendering helper for thumbnails.** Rejected: presentation is not extraction (see
  ADR-005).
- **Let the parity tests keep asserting the legacy shape.** Rejected: the contract they froze
  no longer exists; the cases that exercised core behavior through the facade (signature
  rejection, repeated tEXt entries, thumbnail bytes) were moved into typed-API tests first.

## Consequences

- One public API to document, version and support; every doc locale and the CHANGELOG tell the
  same story in the same change.
- v1 consumers who want the old shape migrate deliberately (README table) or stay on `^1.0`;
  nothing half-works in the middle.
- The library can drop the quirk lock: the typed document always exposes
  `header->interlaceMethod`, which is a *change* in output relative to v1 and is listed as
  BREAKING in the CHANGELOG.
- Future majors lose the "facade as cushion" pattern; if a Phase-4 Laravel package wants
  array access, it lives there.
- `openspec/specs/legacy-compatibility/` was retired at archive time: the capability no longer
  has requirements to hold.

## Sources

`remove-v1-compatibility` design D1–D4, D6, D7; README "Upgrading from v1"; CHANGELOG 2.0.0
`Removed`; AGENTS.md §4 and §10.
