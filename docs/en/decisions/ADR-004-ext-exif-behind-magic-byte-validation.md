---
title: ADR-004 ext-exif behind magic-byte validation
lang: en-US
---

# ADR-004: `ext-exif` behind magic-byte validation

## Status
Accepted

## Date
2026-08-12 (recorded 2026-11-03)

## Context

The `eXIf` chunk carries a TIFF structure — the same bytes an JPEG APP1 segment would
carry, minus the `Exif\0\0` ID code. PHP already decodes it with `exif_read_data()`, but v1
called the extension directly on the chunk payload, so a malformed profile surfaced as a
PHP warning or a `TypeError` from a `false` return, and one bad chunk could abort the read.

## Decision

Keep the extension and put a validation gate in front of it. `ExifDecoder` checks the four
TIFF magic bytes (`II\x2A\x00` little-endian, `MM\x00\x2A` big-endian — spec §11.3.4.5.2)
before touching the extension, then feeds the payload through a `php://temp` stream to
`exif_read_data()`. A bad magic sequence or a failed decode raises
`ChunkDecodingException` (`invalid TIFF header: expected "II*\0" or "MM\0*" magic bytes`),
whose treatment is decided by the mode (ADR-007).

## Alternatives Considered

- **Hand-written TIFF/EXIF parser to drop the extension.** Rejected: high effort, high risk
  of mis-decoding vendor tags, and `ext-exif` ships in essentially every PHP build (it is a
  required extension of this package).
- **Call `exif_read_data()` on the raw payload without validating.** Rejected: the
  extension's failure mode is a suppressed warning plus `false`, which is exactly the silent
  corruption v1 exhibited, and the PNG spec recommends the check.
- **Skip EXIF entirely and expose only the bytes.** Rejected: decoded EXIF sections are the
  main reason consumers use this library.

## Consequences

- The only place the package talks to `ext-exif` for profile decoding is one boundary class,
  so a future switch to a pure-PHP TIFF reader is contained.
- Malformed profiles produce a typed, mode-governed failure naming the chunk and offset
  instead of a warning — which keeps `failOnWarning="true"` in PHPUnit meaningful.
- Output still varies slightly across PHP versions (the extension's own behavior), so the
  suite asserts on stable keys and the CI matrix covers 8.3/8.4/8.5.
- `thumbnailBytes()` reuses the same extension boundary (ADR-005).

## Sources

`modernize-architecture` design D3; `unify-decode-failure-policy` design D1;
`metadata-extraction` spec ("eXIf decoding with magic-byte validation");
`src/Chunk/ExifDecoder.php`.
