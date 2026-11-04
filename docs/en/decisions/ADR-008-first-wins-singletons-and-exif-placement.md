---
title: ADR-008 First-wins singletons and eXIf placement
lang: en-US
---

# ADR-008: First-wins singletons and eXIf placement

## Status
Accepted

## Date
2026-11-02 (recorded 2026-11-03)

## Context

Two rules of the PNG spec are about *stream shape* rather than payload content, so the
decoder can produce a perfectly good value while the file is still non-conformant:

- sRGB, bKGD, eXIf and the XMP-packet `iTXt` entry are allowed at most once. Before
  unification, eXIf had a first-wins guard while sRGB, bKGD and XMP silently kept the *last*
  instance — a duplicate could change the answer depending on decoder order.
- PNG Third Edition (§11.3.4.5) places eXIf before the first IDAT. Legacy encoders do not
  always obey, and the profile itself is perfectly readable when it arrives late.

These cases cannot be handled like a malformed payload (ADR-007): throwing unconditionally
would throw away data the lenient reader was explicitly built to keep.

## Decision

Both rules are **mode-aware** and live where the state already is:

- **Singletons**: one per-key guard in `MetadataBuilder::acceptsSingleton($key, …)` over a
  `$singletons` map, used by `setRenderingIntent('sRGB')`, `setBackground('bKGD')`,
  `setExif('eXIf')` and `setXmp('XMP packet')`. First instance wins in both modes; the second
  is silently ignored in lenient mode and raises
  `only one <key> chunk is allowed; the first instance wins.` in strict mode. Adding a
  Phase-2 singleton (gAMA, cHRM, tRNS, iCCP, pHYs…) is one call, not another field-level `if`.
- **eXIf placement**: the reader notes image data as soon as it dispatches `IDAT`/`fdAT`
  (`DecodingContext::noteImageData()`), and `ExifDecoder` rejects the chunk only when
  `$context->strict && $context->sawImageData()` with
  `eXIf must appear before the first IDAT chunk (PNG Third Edition, §11.3.4.5).` Lenient mode
  decodes it.
- tEXt and non-XMP iTXt stay repeatable — the spec allows them, and entries are appended in
  file order.

## Alternatives Considered

- **Reject duplicates in both modes.** Rejected: under-extracts in lenient mode; the data is
  usable and the file is only non-conformant.
- **Last-wins.** Rejected: depends on iteration order, is impossible to explain, and was the
  pre-unification accident rather than a decision.
- **Check placement by absolute offset arithmetic in the builder.** Rejected: the fact "image
  data has been seen" belongs to the stream-position context, not to the accumulator.

## Consequences

- The singleton set is a list of keys in one file, so Phase-2 chunks join with one line each
  and cannot forget the guard.
- Strict mode now fails files that lenient mode still reads correctly — intentional, and the
  reason both behaviors are asserted in the same test case.
- Duplicate-driven ambiguity disappears from the document: the first decoded value is
  deterministically the one exposed.
- Lenient consumers of a late-eXIf file get EXIF that a strict conformance checker rejects;
  the difference is documented (`metadata-extraction` spec) rather than hidden.

## Sources

`unify-decode-failure-policy` design D5, D6; `metadata-extraction` spec ("First-wins singleton
chunks", "eXIf decoding with magic-byte validation"); `src/Chunk/MetadataBuilder.php`;
`src/Chunk/ExifDecoder.php`; `src/Chunk/DecodingContext.php`.
