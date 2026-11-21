# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0-beta.3] - 2026-11-20

### Security

- Bound `zTXt` and compressed `iTXt` inflation with a cumulative, per-read
  budget (16 MiB by default), preventing zlib-bomb payloads from exhausting
  memory. A chunk that exceeds the remaining budget follows the unified
  lenient/strict policy. The budget is configurable through
  `PngMetadataReader::create(maxInflatedBytes: ...)`.

## [2.0.0-beta.2] - 2026-11-19

### Added

- `zTXt` text chunk decoding: Latin-1 keyword and zlib-compressed text are
  inflated and converted to UTF-8, exposed through the existing `texts` array
  with `chunkType: 'zTXt'` and `compressed: true`. Multiple entries are kept in
  file order; malformed payloads follow the unified lenient/strict policy.

### Fixed

- XMP packets that use the default XML namespace no longer return `null`:
  unprefixed properties are grouped under `xmp['default']`, and elements with
  no namespace are not misattributed to that group.

## [2.0.0-beta.1] - 2026-11-15

> First public pre-release of v2 for testing. The API may still change before
> `2.0.0` stable; feedback goes through the `v2-beta` issue template/label.

Version 2 is a ground-up rewrite on a layered architecture with a typed,
immutable API. This is a **breaking release**: the v1 `PNGMetadata` array
facade is removed and the typed core (`PngMetadataReader` → `MetadataDocument`)
is the only public API. The v1→v2 migration map lives in the README
("Upgrading from v1"); the `1.x` line remains available for legacy consumers.

### Added

- Layered core: binary parsing (`PNGMetadata\Parsing`), per-chunk decoders
  (`PNGMetadata\Chunk`), and a typed, immutable domain model
  (`PNGMetadata\Metadata`) exposed through `PNGMetadata\PngMetadataReader`.
- Typed value objects and enums: `HeaderData`, `ColorType`, `InterlaceMethod`,
  `RenderingIntent`, `TextEntry`, `BackgroundColor`, `MetadataDocument`.
- Flexible input: the core accepts a file path, a binary string, or a stream
  resource (not only disk paths).
- Strict validation mode (`PngMetadataReader::create(strict: true)`) with
  per-chunk CRC-32 verification and critical ordering rules (IHDR first, IEND last).
- Unified decode-failure policy: lenient mode omits corrupt chunks (unknown
  ancillary chunks are never an error) and always completes extraction; strict
  mode now reports every decode-conformance violation — malformed payloads,
  duplicated singleton chunks (sRGB/bKGD/eXIf/XMP) and late-placed eXIf (PNG
  Third Edition requires eXIf before the first IDAT; lenient mode still decodes
  it for interoperability) — with the chunk type and byte offset. IHDR failures
  are fatal in both modes.
- Lazy chunk payloads: `IDAT`/`fdAT` image data can be skipped without loading
  it into memory, so metadata extraction cost stays proportional to metadata size.
- Domain exception taxonomy under `PNGMetadata\Exception\PngMetadataException`.
- Positional iTXt decoding per spec: compression flag/method, BCP47 language tag,
  translated keyword, and zlib-compressed text support.
- XMP decoding from the iTXt `XML:com.adobe.xmp` keyword into a structured array;
  invalid XMP no longer aborts extraction of the remaining metadata.
- PHPUnit 11 test suite with a programmatic PNG fixture builder and PHPStan level
  max static analysis. CI matrix on PHP 8.3 / 8.4 / 8.5.

### Changed

- **BREAKING**: minimum PHP version raised from `^7.4 || ^8.0` to `^8.3`.
- Declared the previously-implicit `ext-xml`, `ext-mbstring`, and `ext-zlib`
  requirements.
- Replaced the abandoned PHPStan 0.12 stack (phpstan-nette, disallowed-calls)
  with PHPStan 2 at level max.
- tEXt keywords and text are converted from ISO-8859-1 (Latin-1, per spec) to
  UTF-8 on output.
- **BREAKING**: IHDR validation now also rejects zero image width/height and
  compression/filter methods other than `0` (spec §11.2.1). Like every IHDR
  failure this is fatal in both reader modes, so previously-accepted invalid
  headers now raise `ChunkDecodingException` even in lenient mode.
- Strict mode is a real conformance gate: unknown critical chunk types
  (outside IHDR/PLTE/IDAT/IEND/fdAT), bKGD payloads longer than the color type
  requires, and iTXt compression fields outside the defined domain now raise
  domain failures. Lenient mode is unchanged for all three.

### Fixed

- `exif_read_data()` returning `false` caused a `TypeError` on PHP 8; a failed
  EXIF decode now simply omits the EXIF section instead of being fatal.
- The eXIf chunk is now validated against the TIFF magic bytes (`II*\0` /
  `MM\0*`) before decoding, as recommended by the PNG spec.
- iTXt parsing no longer relies on null-byte trimming (which corrupted
  compressed XMP packets); it parses the chunk positionally per spec.
- Truncated datastreams and short reads now raise a domain exception instead of
  emitting partial data.
- The single-instance rule for the eXIf chunk is enforced (first one wins).
- A malformed iTXt chunk no longer aborts the whole extraction in lenient mode;
  it is omitted like any other corrupt chunk.
- An uninterpretable bKGD (no IHDR context yet, or a short payload) raises a
  policy-governed domain failure instead of crashing with a logic error.
- Duplicated singleton chunks are deterministically first-wins (previously
  sRGB, bKGD and XMP silently kept the last instance).
- tEXt/iTXt keywords are validated against the spec bounds (1-79 bytes) and
  tEXt payloads must carry the null terminator; violations follow the policy.
- A duplicated IHDR later in the stream no longer overwrites the header, and a
  malformed one no longer aborts lenient extraction; IHDR is now first-wins
  with strict mode reporting the duplicate.
- The XMP singleton slot is claimed by the first well-formed packet even when
  it carries no properties, so a later packet can no longer slip past
  strict-mode duplicate detection.

### Deprecated

- None.

### Removed

- **BREAKING**: the `PNGMetadata` facade (`src/PNGMetadata.php`) and its entire
  v1 surface: `ArrayObject` inheritance, `extract()`, the legacy `toArray()`
  shape, colon-path `get('a:b:c')`, `__toString()` two-column rendering,
  `getThumbnail()` (returns `\GdImage`), `isPNG()`, `getType()`, and the
  `InvalidArgumentException` codes 101/102/103. Use
  `PngMetadataReader::create()->read()` with the typed exceptions and recipes
  from the README migration guide.
- **BREAKING**: `LegacyArrayFormatter` and the v1 output-parity contract,
  including the legacy `IHDR` quirk that omitted the `Interlace` key. The typed
  document always exposes `header->interlaceMethod`.
- **BREAKING**: all GD coupling. `ext-gd` no longer appears in the package
  (the `suggest` entry is gone); embedded thumbnails are served as bytes via
  `MetadataDocument::thumbnailBytes()`.

[2.0.0-beta.3]: https://github.com/joserick/PNGMetadata/compare/v2.0.0-beta.2...v2.0.0-beta.3
[2.0.0-beta.2]: https://github.com/joserick/PNGMetadata/compare/v2.0.0-beta.1...v2.0.0-beta.2
[2.0.0-beta.1]: https://github.com/joserick/PNGMetadata/compare/v0.2.8...v2.0.0-beta.1
