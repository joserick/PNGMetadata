---
title: Architecture decision records
lang: en-US
---

# Architecture decision records

The reasoning behind PNGMetadata's architecture: what was decided, why, what was
rejected, and what it costs. Code shows *what*; these records explain *why it is
this way*, so the same decision does not get re-litigated in the next refactor.

The behavioral contract lives in the specs (`openspec/specs/`). Where a record and a
spec differ, **the spec is normative** and the record is history.

## Index

| ADR | Title | Status |
|---|---|---|
| [001](/en/decisions/ADR-001-two-layers-plus-typed-entry-point.html) | Two layers plus a typed entry point | Accepted |
| [002](/en/decisions/ADR-002-lazy-chunk-payloads.html) | Lazy chunk payloads | Accepted |
| [003](/en/decisions/ADR-003-one-decoder-per-chunk-type.html) | One decoder per chunk type | Accepted |
| [004](/en/decisions/ADR-004-ext-exif-behind-magic-byte-validation.html) | `ext-exif` behind magic-byte validation | Accepted |
| [005](/en/decisions/ADR-005-no-gd-thumbnails-as-bytes.html) | No GD: thumbnails as bytes | Accepted |
| [006](/en/decisions/ADR-006-typed-exception-taxonomy.html) | Typed exception taxonomy | Accepted |
| [007](/en/decisions/ADR-007-mode-governed-decode-failure-policy.html) | Mode-governed decode-failure policy | Accepted |
| [008](/en/decisions/ADR-008-first-wins-singletons-and-exif-placement.html) | First-wins singletons and eXIf placement | Accepted |
| [009](/en/decisions/ADR-009-v1-compatibility-removed-outright.html) | v1 compatibility removed outright | Accepted |
| [010](/en/decisions/ADR-010-test-lanes-and-ratcheted-coverage-gate.html) | Test lanes and a ratcheted coverage gate | Accepted |

## Anatomy of a record

Every ADR uses the same sections, in this order:

```
# ADR-00N: <title>
## Status            Accepted | Proposed | Superseded by ADR-… | Deprecated
## Date              when the decision was taken (recorded date when later)
## Context           the forces that shaped it
## Decision          what was chosen, in one paragraph of imperative prose
## Alternatives Considered   each option with its rejection reason
## Consequences      what this makes easy, what it makes harder
## Sources           design sections, specs and code the record describes
```

## House rules

- Numbering is append-only: new records take the next free number and never reuse
  one. Retiring a decision changes its `Status`, not its number.
- Nothing is deleted. When a decision is replaced, a new record supersedes the old
  one and the old one points at it.
- Both locales mirror the same filenames. A record added under `en/` must land in
  [`/es/decisions/`](/es/decisions/README.html) with the same basename.
