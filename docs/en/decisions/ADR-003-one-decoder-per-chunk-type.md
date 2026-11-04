---
title: ADR-003 One decoder per chunk type
lang: en-US
---

# ADR-003: One decoder per chunk type

## Status
Accepted

## Date
2026-08-12 (recorded 2026-11-03)

## Context

v1 ran six extractors inline in the reader class, so supporting a chunk meant editing the
class that also framed the stream, and Phase-2/3 chunk work (zTXt, iCCP, gAMA, cICP, APNG…)
would have kept growing that file. Some chunks also cannot be interpreted in isolation:
`bKGD` is 1, 2 or 6 bytes depending on the IHDR color type, so decoding order and shared
state matter.

## Decision

One class per chunk type behind `ChunkDecoderInterface` (`supports()` +
`decode(RawChunk, DecodingContext, MetadataBuilder)`), collected by `ChunkDecoderRegistry`,
whose `defaults()` registers `IhdrDecoder`, `TextDecoder`, `ItxtDecoder`, `ExifDecoder`,
`SrgbDecoder` and `BkgdDecoder`. Two supporting rules:

- `DecodingContext` carries what later decoders need: the decoded `HeaderData`
  (`requireHeader()`), the stream's strict flag and whether image data has been seen.
  `IhdrDecoder` writes into it; `BkgdDecoder` (and later `tRNS`) read from it.
- `MetadataBuilder` is the only accumulator: decoders never talk to each other. A chunk
  that is not XMP-by-keyword but XMP-in-practice (`XML:com.adobe.xmp` inside an `iTXt`)
  stays a method of `ItxtDecoder`, delegating to the `XmpDecoder` helper — it is not a
  registry entry, because the chunk type it decodes is already registered.

## Alternatives Considered

- **A `match` on chunk type inside the reader.** Rejected: recreates the monolith one level
  down, and puts per-chunk policy next to framing.
- **Decoders returning values that the reader assembles.** Rejected: the reader would own
  merge rules (duplicates, ordering, first-wins), which is exactly what `MetadataBuilder`
  isolates.
- **Dependency-injecting the header into each decoder constructor.** Rejected: decoders are
  stateless and reused across files; per-file state belongs in the context.

## Consequences

- Adding a chunk is one class plus one registry line; nothing else in the reader changes.
- Unknown chunk types cost nothing: they are never decoded and stay available as raw chunks
  with their spec property flags (`ChunkType`, spec §5.4).
- Interpretation order is now load-bearing: a decoder registered before `IHDR` would find no
  context, so the builder/context surface is deliberately narrow and `requireHeader()` fails
  as a domain exception rather than a crash.
- Per-chunk behavior lives in per-chunk files, which is where Phase-2 chunk work lands.

## Sources

`modernize-architecture` design D2 and D7 (chunk bible); AGENTS.md §5;
`src/Chunk/` (`BkgdDecoder`, `ChunkDecoderInterface`, `ChunkDecoderRegistry`,
`DecodingContext`, `ExifDecoder`, `IhdrDecoder`, `ItxtDecoder`, `MetadataBuilder`,
`SrgbDecoder`, `TextDecoder`, `XmpDecoder`).
