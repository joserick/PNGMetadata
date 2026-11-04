---
title: ADR-001 Two layers plus a typed entry point
lang: en-US
---

# ADR-001: Two layers plus a typed entry point

## Status
Accepted

## Date
2026-08-12 (recorded 2026-11-03)

## Context

v1 shipped as a single 670-line class (`src/PNGMetadata.php extends ArrayObject`) that
validated the input, scanned the chunk stream and ran six hardcoded extractors from its
constructor. Every fix touched all three concerns at once, and the class could not be
reused as the framework-free core of the planned Laravel package, which must also work on
streams without disk access.

## Decision

Keep two layers plus one typed entry point, and make every dependency point inward:

- **Domain** (`src/Metadata/`): `final readonly` value objects and enums. No I/O, no byte
  knowledge.
- **Parsing** (`src/Parsing/`, `src/Chunk/`, `src/Exception/`): the only layer that touches
  bytes — stream normalization, chunk framing, per-type decoding, typed failures.
- **`PngMetadataReader::read()`**: the only public entry point, returning `MetadataDocument`.

The model never references the parser; the entry point is the only place where the two are
wired together.

## Alternatives Considered

- **Refactor in place, keep one class.** Rejected: the monolith is the root cause of the
  fragility being fixed, not an accident of it.
- **Publish core and facade as two packages.** Rejected for now: one repo and one package
  stay simple; the split can happen later if the Laravel package needs it.
- **A facade layer above the typed model** (v1 array shape, `ArrayObject`). Implemented in
  the first v2 iteration and then removed — see ADR-009.

## Consequences

- Adding a chunk type means adding one decoder class (ADR-003); the model and the entry
  point do not change.
- The domain model is testable without any file, and the parser is testable without any
  interpretation knowledge.
- New contributors must learn three directories before the code reads naturally; AGENTS.md
  §3 and the layered-architecture page carry the map.
- Consumers get exactly one API surface, which makes the typed model the compatibility
  boundary for every future release.

## Sources

`modernize-architecture` design D1
(`openspec/changes/archive/2026-08-12-modernize-architecture/design.md`); AGENTS.md §3;
`src/PngMetadataReader.php`.
