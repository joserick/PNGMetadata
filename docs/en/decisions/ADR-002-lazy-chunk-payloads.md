---
title: ADR-002 Lazy chunk payloads
lang: en-US
---

# ADR-002: Lazy chunk payloads

## Status
Accepted

## Date
2026-08-12 (recorded 2026-11-03)

## Context

A PNG file is image data with a few bytes of metadata attached: a 50 MB screenshot can
carry under 2 KB of text chunks, and IDAT is typically one huge chunk or many consecutive
ones. Metadata extraction has to walk the whole file, so the naive framing loop — read
header, read payload, return a struct — would move the entire image through memory to look
at its labels. The `png-parsing` capability specifies the opposite: image data chunks are
skippable.

## Decision

`RawChunk` stores only what the framing pass already knows (type, absolute offset,
declared length, payload offset) and materializes the payload on demand in `data()`, which
seeks to the payload, reads it and **restores the cursor before returning** — so iteration
stays position-neutral whether or not a payload was read. The reader never calls `data()`
for `IDAT`/`fdAT`.

Cost of iterating a file is therefore proportional to chunk *headers*, not to image size.

## Alternatives Considered

- **Eager payload during framing.** Rejected: `memory_get_peak_usage()` grows with the
  image, and the library's only job is metadata.
- **A second, metadata-only iterator that skips image data entirely.** Rejected: two code
  paths over the same stream, and unknown ancillary chunks would need to be classified
  before their payloads could be skipped.
- **Memory-map the input.** Rejected: adds platform-specific behavior and a file must exist,
  while streams and binary strings are supported inputs.

## Consequences

- Large files extract in constant memory; the guarantee is asserted by the `performance`
  lane (`composer test:performance`), which builds a single 50 MB `IDAT` outside the
  measured window, iterates `read()`, and asserts peak usage stays below the payload size
  plus ~14 MB — i.e. the image data never leaks into the read loop.
- Payload access is a seek away, so decoders may call `data()` freely — but each call
  re-reads. Acceptable: metadata chunks are small and decoded once.
- In strict mode, CRC verification (`computeCrc()`) must materialize every chunk including
  image data. Accepted trade-off: validating CRCs is the point of strict mode, and the user
  opted into the cost.
- `data()` must stay position-neutral. Any future change that leaves the cursor elsewhere
  silently corrupts the iteration that follows it.

## Sources

`modernize-architecture` design D2; `png-parsing` spec ("Image data chunks are skippable");
`src/Parsing/RawChunk.php`; `tests/Unit/Parsing/LargeIdatMemoryTest.php`.
