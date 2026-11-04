---
title: ADR-006 Typed exception taxonomy
lang: en-US
---

# ADR-006: Typed exception taxonomy

## Status
Accepted

## Date
2026-08-12 (recorded 2026-11-03)

## Context

v1 signalled every failure with `InvalidArgumentException` plus a magic number (101, 102,
103, 104) whose meaning lived only in the facade's code, and mixed it with raw PHP warnings
and `TypeError`s from the extractors. A consumer could not distinguish "this file is not a
PNG" from "one chunk is corrupt" without reading v1's source, and the numbers could not be
documented honestly because two different failures shared code semantics.

## Decision

Every failure is a class under `PNGMetadata\Exception\` implementing the marker interface
`PngMetadataException` (which extends `\Throwable`), so one `catch` still covers the library:

| Class | Meaning | Base |
|---|---|---|
| `InvalidPngException` | input is not a PNG / unusable input | `\InvalidArgumentException` |
| `TruncatedDataException` | stream ended before the declared bytes | `\RuntimeException` |
| `CrcMismatchException` | chunk CRC disagrees (strict mode) | `\RuntimeException` |
| `ChunkDecodingException` | a chunk payload violated the spec (strict mode) | `\RuntimeException` |

Input rejection extends `\InvalidArgumentException` because it *is* the caller's argument;
everything downstream of framing extends `\RuntimeException` because the caller did nothing
wrong. There are no numeric codes: `getCode()` is not part of the contract, and the message
carries the evidence (chunk type and byte offset — see ADR-007).

## Alternatives Considered

- **One generic exception with error codes.** Rejected: keeps the magic numbers alive and
  forces string matching to know what failed.
- **Throw plain SPL exceptions only.** Rejected: consumers could not catch "any library
  error" without also swallowing unrelated `\RuntimeException`s from their own code.
- **Preserve v1's codes 101–104 on the typed classes** (`modernize-architecture` D5).
  Superseded by ADR-009: the codes belonged to the facade's contract, and the facade is gone.

## Consequences

- The failure vocabulary is greppable and documentable; `api.md` lists the four classes as
  the error contract.
- Tests assert on types (`expectException(ChunkDecodingException::class)`), not messages or
  codes, so wording can improve without breaking consumers.
- The `strict` mode boundary is expressible in one place: only violations the mode may
  forgive use `ChunkDecodingException`/`CrcMismatchException` (ADR-007).
- Anyone adding a failure type must keep it under the interface, which AGENTS.md §4 states as
  the error contract for the package.

## Sources

`modernize-architecture` design D5; `remove-v1-compatibility` design D4 (codes dropped);
AGENTS.md §4; `src/Exception/`.
