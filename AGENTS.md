# AGENTS.md

Guidance for AI agents (and contributors) working on **PNGMetadata** — a native PHP
library that extracts metadata (XMP, EXIF, IHDR, sRGB, bKGD, text) from PNG images
without external tools like ExifTool.

> Read this before making changes. It captures the architecture, conventions, and
> workflows that are not obvious from the code alone.

---

## 1. What this project is

- **Package:** `joserick/png-metadata` (Packagist), GPL-3.0-only.
- **Type:** framework-free PHP library. It is intended to be the **core** for a future
  Laravel package (separate repo), so it must stay free of framework dependencies.
- **Current line:** v2 (`2.0.0`), a ground-up rewrite. v1 lives on the `1.x` line for
  legacy PHP (7.4/8.0). v1 receives security fixes only.
- **Read-only:** the library reads metadata; it does **not** write or edit PNG files,
  and never decodes pixel data (IDAT/fdAT contents are skipped).

## 2. Tech stack & requirements

- **PHP `^8.3`** (enums, readonly classes, typed constants). CI runs 8.3 / 8.4 / 8.5.
- **Required extensions:** `ext-exif`, `ext-mbstring`, `ext-xml`, `ext-zlib`.
- **No optional extensions:** GD was removed entirely in 2.0.0 (embedded
  thumbnails are returned as bytes by the typed core).
- **Dev:** PHPUnit 11, PHPStan 2 (level **max**), `roave/security-advisories`.
- **Normative reference:** PNG Specification, Third Edition (W3C Recommendation,
  2025-06-24). When in doubt about a chunk, the spec is the source of truth.

## 3. Architecture (two layers + typed entry point, dependency rule points inward)

```
┌────────────────────────────────────────────────────────────┐
│ Domain (typed model, NO I/O, NO GD)                        │
│   src/Metadata/  MetadataDocument, HeaderData, TextEntry,  │
│                  BackgroundColor, enums {ColorType,        │
│                  InterlaceMethod, RenderingIntent}         │
├────────────────────────────────────────────────────────────┤
│ Parsing (the ONLY layer that touches bytes)                │
│   src/Parsing/   PngReader, RawChunk, ChunkType,           │
│                  BinaryStream                              │
│   src/Chunk/     ChunkDecoderInterface + one decoder per   │
│                  chunk type, ChunkDecoderRegistry,         │
│                  DecodingContext, MetadataBuilder          │
│   src/Exception/ PngMetadataException interface + types    │
└────────────────────────────────────────────────────────────┘
```

### Data flow

```
input (path|string|stream)
  → BinaryStream          normalize to a seekable stream
  → PngReader             signature check + chunk framing → yields RawChunk (lazy payload)
  → ChunkDecoderRegistry  per-type decoder(rawChunk, DecodingContext, MetadataBuilder)
  → MetadataBuilder       mutable accumulator, frozen at end
  → MetadataDocument      immutable typed model   ← the public API (PngMetadataReader::read)
```

### Key design decisions (do not regress these)

- **Lazy payloads:** `RawChunk::data()` seeks on demand and restores the cursor, so
  iterating a large image costs memory proportional to chunk *headers* only. Never load
  IDAT/fdAT eagerly. `data()` must stay position-neutral.
- **IHDR as context:** some chunks (bKGD, and in future tRNS) cannot be interpreted
  without the IHDR color type. `IhdrDecoder` writes into `DecodingContext`; later
  decoders read it via `requireHeader()`.
- **Failure policy (mode-governed):** decoders raise `ChunkDecodingException::forChunk`
  (chunk type + byte offset in the message) on any malformed payload; `PngMetadataReader`
  omits the chunk in lenient mode — extraction of the remaining metadata must always
  succeed — and rethrows in strict mode. IHDR failure is fatal in both modes (it is the
  interpretation context). Unknown ancillary chunks are never an error. Singleton chunks
  (sRGB, bKGD, eXIf, XMP packet) are first-wins; strict mode reports the duplicate.
  Late eXIf (after the first IDAT, forbidden by PNG 3rd ed.) is rejected only in strict.
- **eXIf validation:** verify TIFF magic bytes (`II*\0` / `MM\0*`) before decoding
  (PNG spec §11.3.4.5.2). Single eXIf instance; first one wins.
- **Charsets:** tEXt is ISO-8859-1 (Latin-1) per spec → converted to UTF-8 on output.
  iTXt text is UTF-8 and is **not** null-terminated (length comes from chunk length) —
  parse it positionally, never by null-trimming the whole payload.

## 4. Public API (typed, v2 — the only API)

`PngMetadataReader::create(bool $strict = false)->read($input): MetadataDocument`
accepts a file path, binary string, or stream resource. Strict mode enables
per-chunk CRC-32, ordering, and decode-conformance validation (malformed
chunks, duplicated singletons, eXIf placement).

`MetadataDocument` (final readonly) exposes: `header` (`HeaderData`: width,
height, bitDepth, `ColorType`, compression/filter, `InterlaceMethod`), `texts`
(`TextEntry[]` in file order, repeated keywords preserved), `xmp` (structured
array), `exif` (decoded sections), `background` (`BackgroundColor`),
`renderingIntent` (`RenderingIntent`), `thumbnailBytes(): ?string` (no GD), and
`toArray()`. Errors are typed exceptions under `PngMetadataException`
(`InvalidPngException`, `TruncatedDataException`, `ChunkDecodingException`,
`CrcMismatchException`).

### v1 compatibility — intentionally removed

The `PNGMetadata` facade (`ArrayObject`, colon-path `get()`, legacy array
output, `\GdImage` thumbnails, `InvalidArgumentException` codes 101/102/103) and
the legacy output contract were removed in 2.0.0 (openspec change
`remove-v1-compatibility`). Do not reintroduce them: the typed model is the
consumer contract, and the `1.x` branch remains the home for the old API.

## 5. Where things live

| Path | Purpose |
|---|---|
| `src/Parsing/` | byte-level stream + chunk framing (only I/O layer) |
| `src/Chunk/` | one decoder per chunk type + registry + builder + context |
| `src/Metadata/` | immutable VOs + enums (pure domain) |
| `src/Exception/` | `PngMetadataException` interface + typed exceptions |
| `src/PngMetadataReader.php` | the public entry point (wires parser → registry → document) |
| `tests/Fixtures/PngBuilder.php` | **programmatic** PNG fixture builder (no binary images in repo) |
| `tests/Unit/` | PHPUnit tests, mirroring `src/` structure |
| `openspec/` | spec-driven change management (see §7) |

## 6. Commands

```bash
composer test      # PHPUnit 11 (unit suite; excludes the `performance` group)
composer coverage  # unit suite with pcov: text summary + coverage.xml
php tools/check-coverage.php  # coverage gate: fails below the committed BASELINE
composer test:performance      # heavy memory-bound scenario (50 MB IDAT)
composer test:integration      # PngSuite corpus when PNGSUITE_DIR is set (skips otherwise)
composer phpstan   # PHPStan level max
composer validate  # composer.json schema check
```

**`composer test` + `composer phpstan` + the coverage gate must be green before
considering work done.** Before a release, also run `composer test:performance`
and, if a local corpus is available, `composer test:integration` with
`PNGSUITE_DIR` set.

### Environment note (this machine)

There is **no local PHP/Composer binary** on the host. A Docker dev image is used:
- Image: `pngmeta-dev`, built from the repo's `Dockerfile` (`php:8.3-cli` + exif +
  pcov for coverage; xml/mbstring/zlib ship in the base; GD is absent by design).
- Rebuild after Dockerfile changes: `docker build -t pngmeta-dev .`
- Run tests/static analysis via:
  `docker run --rm -v "$PWD":/app pngmeta-dev bash -lc 'composer test && composer phpstan'`

## 7. OpenSpec workflow (spec-driven changes)

This repo uses **OpenSpec** (`openspec/`, schema `spec-driven`) for non-trivial changes.

- **Main specs** (the behavior contract): `openspec/specs/<capability>/spec.md`.
  Current capabilities: `png-parsing`, `metadata-extraction` (the former
  `legacy-compatibility` capability was removed in 2.0.0).
- **Changes** (work in flight): `openspec/changes/<name>/` with `proposal.md`,
  `design.md`, `tasks.md`, `specs/` (deltas). Archived under `openspec/changes/archive/`.
- Useful commands: `openspec list --json`, `openspec status --change <name> --json`,
  `openspec validate --specs --strict`.

**When changing externally visible behavior**, update the corresponding capability spec
via a change (delta), not just the code. Pure internal refactors that keep behavior
identical do not need a spec delta.

## 8. Coding conventions

- `declare(strict_types=1);` at the top of every PHP file. Namespace `PNGMetadata\`.
- **Tabs** for indentation (matches existing files).
- Domain objects are `final readonly` where possible; enums for fixed vocabularies.
- Global functions are called with a leading backslash (`\sprintf`, `\ord`, …).
- DocBlocks document *why* and reference spec sections (e.g. `spec section 11.3.3.4`).
- Decoders implement `ChunkDecoderInterface` and are registered in
  `ChunkDecoderRegistry::defaults()`. **Adding a new chunk = one decoder class +
  one registry line** — do not special-case chunks in the reader.
- Tests use PHPUnit 11 attributes (`#[Test]`, `#[CoversClass]`), not `@test` annotations.
- Coverage attribution: PHPUnit only counts lines of classes declared with
  `#[CoversClass]`; `#[UsesClass]` documents collaborators but adds nothing to
  the measured total. Integration-style tests must list every subsystem they
  exercise as `#[CoversClass]`, or the gate silently under-counts.
- Fixtures are generated with `PngBuilder` (handcrafted chunk writers); never commit
  real photos or third-party images (licensing / personal data).

## 9. Roadmap (future changes — design already documented)

The chunk bible and architecture for these live in
`openspec/changes/archive/2026-08-12-modernize-architecture/design.md` (D7, D8):

- **Phase 2 — classic coverage:** zTXt, iCCP, gAMA, cHRM, tRNS, tIME, pHYs, sBIT +
  the color-precedence resolver (spec §4.3 Table 1: cICP > iCCP > sRGB > cHRM+gAMA).
- **Phase 3 — PNG 3.0:** cICP / mDCV / cLLI (HDR signaling, H.273 enums), APNG summary
  (acTL/fcTL), `image/apng`.
- **Phase 4 — Laravel package** (separate repo) consuming this core.

## 10. Guardrails for agents

- **Do not reintroduce v1 compatibility** (the facade, legacy array output, or
  numeric error codes) — removed by design in 2.0.0; the typed API is the contract.
- **Do not decode pixel data** (IDAT/fdAT) — out of scope by design.
- **Do not add framework dependencies** — this is a framework-free core.
- Keep `composer test` + `composer phpstan` green; add tests for every behavior change.
- Minimal, focused changes; follow the layered architecture (no I/O above the parsing layer).
