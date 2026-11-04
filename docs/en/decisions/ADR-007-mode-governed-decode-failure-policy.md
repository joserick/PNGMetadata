---
title: ADR-007 Mode-governed decode-failure policy
lang: en-US
---

# ADR-007: Mode-governed decode-failure policy

## Status
Accepted

## Date
2026-11-02 (recorded 2026-11-03)

## Context

Before v2 shipped, failure handling was three ad-hoc mechanisms that disagreed with each
other and with the specs:

| Case | Behaviour then |
|---|---|
| Malformed IHDR | throw (fatal) |
| Corrupt eXIf / bad magic | silently omitted, in **both** modes |
| Malformed XMP packet | silently omitted, in both modes |
| Malformed `iTXt` payload | throw — aborting the whole document |
| Short `bKGD` payload | silently omitted |
| `bKGD` with no IHDR context | `\LogicException` crash |

So one corrupt `iTXt` could destroy an otherwise readable file, while strict mode — whose
purpose is to report conformance — stayed silent about most violations. `strict` lived only
in `PngReader` (framing/CRC) and never reached the decode layer. The normative anchor is PNG
Third Edition: an invalid *ancillary* chunk is never a reason to reject the file.

## Decision

Decoders express violations; the reader decides treatment, in exactly one place
(`PngMetadataReader::read()`):

- **Unusable-data violations** (bad separators, failed inflation, bad TIFF magic, invalid
  keyword, unknown intent byte, short payload): the decoder *always* throws
  `ChunkDecodingException::forChunk($code, $offset, $reason)`. Strict rethrows; lenient omits
  that chunk and continues. IHDR rethrows in both modes — without the header, later chunks
  have no interpretation context.
- **Usable-data violations** (duplicate singleton, eXIf after IDAT): the data is fine and the
  stream rule is not, so lenient must still produce the value. These checks are mode-aware and
  read `DecodingContext::$strict` / the builder's own flag.
- **Stream-level failures** are outside the policy: `TruncatedDataException` is fatal in both
  modes (past a short read the iterator cannot trust framing again), and
  `CrcMismatchException` can only exist where CRCs are verified — strict mode only.

## Alternatives Considered

- **Each decoder consults a strict flag and decides.** Rejected: the policy scatters across N
  decoders and every Phase-2 chunk re-implements it; a choke point keeps Open/Closed — "a new
  chunk is one decoder that throws".
- **Swallow everything, log later.** Rejected: strict mode would have nothing to report, and
  the evidence (chunk type + byte offset) would be gone.
- **Fail fast on any malformed chunk.** Rejected: contradicts the spec's ancillary rule and the
  library's purpose — extract what is there.
- **Return `null` from decoders on failure.** Rejected: loses the reason; `null` cannot say
  "unknown sRGB intent byte 9 at offset 1234".

## Consequences

- Lenient `read()` always yields a document unless IHDR or the stream itself is unusable; the
  failure policy is now one `catch` block to read.
- Strict mode is the conformance checker, with messages shaped
  `Decode failure in chunk "iTXt" at offset 1234: <reason>` — enough evidence to fix the file.
- Consumers who need to know *what was omitted* in lenient mode cannot ask yet: there is no
  observability API, deliberately left as an open question rather than half-built.
- Decoders must never catch their own violation; doing so would silently opt out of strict
  mode.

## Sources

`unify-decode-failure-policy` design D1–D4, D7; `metadata-extraction` spec ("Unified
decode-failure policy"); `src/PngMetadataReader.php`;
`src/Exception/ChunkDecodingException.php`.
