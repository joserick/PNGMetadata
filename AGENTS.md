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
- **Optional:** `ext-gd` — only `PNGMetadata::getThumbnail()` needs it (declared in
  `suggest`, not `require`).
- **Dev:** PHPUnit 11, PHPStan 2 (level **max**), `roave/security-advisories`.
- **Normative reference:** PNG Specification, Third Edition (W3C Recommendation,
  2025-06-24). When in doubt about a chunk, the spec is the source of truth.

## 3. Architecture (three layers, dependency rule points inward)

```
┌────────────────────────────────────────────────────────────┐
│ Facade (compatibility)                                     │
│   src/PNGMetadata.php          extends ArrayObject (v1 API)│
│   src/LegacyArrayFormatter.php domain → v1 array shape     │
├────────────────────────────────────────────────────────────┤
│ Domain (typed model, NO I/O, NO GD, NO ext-exif calls)     │
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
  → MetadataDocument      immutable typed model   ← public new API (PngMetadataReader::read)
  → LegacyArrayFormatter  → v1 array shape        ← public v1 API (PNGMetadata facade)
```

### Key design decisions (do not regress these)

- **Lazy payloads:** `RawChunk::data()` seeks on demand and restores the cursor, so
  iterating a large image costs memory proportional to chunk *headers* only. Never load
  IDAT/fdAT eagerly. `data()` must stay position-neutral.
- **IHDR as context:** some chunks (bKGD, and in future tRNS) cannot be interpreted
  without the IHDR color type. `IhdrDecoder` writes into `DecodingContext`; later
  decoders read it via `requireHeader()`.
- **Failure isolation:** a corrupt EXIF or XMP section is **omitted**, never fatal —
  extraction of the remaining metadata must always succeed.
- **eXIf validation:** verify TIFF magic bytes (`II*\0` / `MM\0*`) before decoding
  (PNG spec §11.3.4.5.2). Single eXIf instance; first one wins.
- **Charsets:** tEXt is ISO-8859-1 (Latin-1) per spec → converted to UTF-8 on output.
  iTXt text is UTF-8 and is **not** null-terminated (length comes from chunk length) —
  parse it positionally, never by null-trimming the whole payload.

## 4. Public API (v1, preserved — do not break)

`PNGMetadata extends ArrayObject`:
- `__construct(string $path)` — throws `InvalidArgumentException` (codes 101 empty,
  102 missing, 103 not-PNG).
- `PNGMetadata::extract(?string $path): ?self` — never throws, `null` on failure.
- `toArray(): array` — legacy grouped array, `ksort`ed.
- `get('a:b:c')` — colon-path access, `null` when missing. Returns raw stored scalars.
- `__toString()` — two-column table, `<pre>` wrapped off-CLI, safe when empty.
- `getThumbnail(): \GdImage|false` — GD only touched here.
- `isPNG(string $path): bool`, `getType(string $path): int|false` (via `exif_imagetype`).

**New typed API (v2):** `PngMetadataReader::create(bool $strict = false)->read($input): MetadataDocument`.
Strict mode enables per-chunk CRC-32 + ordering validation.

### Output parity contract

The legacy array reproduces v1 **exactly**, including quirks. Known intentional quirk:
the `IHDR` group **omits the `Interlace` key** (v1 read 6 of the 7 IHDR fields). The
interlace method is still available on the typed core (`$document->header?->interlaceMethod`).
`tests/Unit/LegacyParityTest.php` freezes this — if it fails after your change, you broke
a consumer-visible behavior.

## 5. Where things live

| Path | Purpose |
|---|---|
| `src/Parsing/` | byte-level stream + chunk framing (only I/O layer) |
| `src/Chunk/` | one decoder per chunk type + registry + builder + context |
| `src/Metadata/` | immutable VOs + enums (pure domain) |
| `src/Exception/` | `PngMetadataException` interface + typed exceptions |
| `src/PNGMetadata.php` | v1 facade over the core |
| `src/PngMetadataReader.php` | v2 entry point (wires parser → registry → document) |
| `src/LegacyArrayFormatter.php` | domain → v1 array shape |
| `tests/Fixtures/PngBuilder.php` | **programmatic** PNG fixture builder (no binary images in repo) |
| `tests/Unit/` | PHPUnit tests, mirroring `src/` structure |
| `openspec/` | spec-driven change management (see §7) |

## 6. Commands

```bash
composer test      # PHPUnit 11
composer phpstan   # PHPStan level max
composer validate  # composer.json schema check
```

**Both `composer test` and `composer phpstan` must be green before considering work done.**

### Environment note (this machine)

There is **no local PHP/Composer binary** on the host. A Docker dev image is used:
- Image: `pngmeta-dev` (built from `php:8.3-cli` + exif/gd/xml/mbstring/zlib + Composer).
- Run tests/static analysis via:
  `docker run --rm -v "$PWD":/app pngmeta-dev bash -lc 'composer test && composer phpstan'`
- If the image is missing, rebuild it (see project maintainer); do not assume host PHP.
- GD in the image has no JPEG support — use `imagegif`/`imagepng` in fixtures.

## 7. OpenSpec workflow (spec-driven changes)

This repo uses **OpenSpec** (`openspec/`, schema `spec-driven`) for non-trivial changes.

- **Main specs** (the behavior contract): `openspec/specs/<capability>/spec.md`.
  Current capabilities: `png-parsing`, `metadata-extraction`, `legacy-compatibility`.
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

- **Do not break the v1 public API** or the legacy array shape (see §4 parity contract).
- **Do not decode pixel data** (IDAT/fdAT) — out of scope by design.
- **Do not add framework dependencies** — this is a framework-free core.
- Keep `composer test` + `composer phpstan` green; add tests for every behavior change.
- Minimal, focused changes; follow the layered architecture (no I/O above the parsing layer).
