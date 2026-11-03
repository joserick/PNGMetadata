# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - Unreleased

Version 2 is a ground-up rewrite on a layered architecture, while preserving the
v1 public API so existing consumers upgrade without code changes.

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
- `ext-gd` moved from `require` to `suggest`; only `PNGMetadata::getThumbnail()`
  needs it. The core returns the embedded thumbnail as a binary string.
- Declared the previously-implicit `ext-xml`, `ext-mbstring`, and `ext-zlib`
  requirements.
- Replaced the abandoned PHPStan 0.12 stack (phpstan-nette, disallowed-calls)
  with PHPStan 2 at level max.
- tEXt keywords and text are converted from ISO-8859-1 (Latin-1, per spec) to
  UTF-8 on output.

### Fixed

- `exif_read_data()` returning `false` caused a `TypeError` on PHP 8; a failed
  EXIF decode now simply omits the EXIF section instead of being fatal.
- The eXIf chunk is now validated against the TIFF magic bytes (`II*\0` /
  `MM\0*`) before decoding, as recommended by the PNG spec.
- iTXt parsing no longer relies on null-byte trimming (which corrupted
  compressed XMP packets); it parses the chunk positionally per spec.
- `__toString()` no longer throws on an empty metadata set.
- Truncated datastreams and short reads now raise a domain exception instead of
  emitting partial data.
- The single-instance rule for the eXIf chunk is enforced (first one wins).

### Deprecated

- None.

### Removed

- None from the public API. The internal monolith was replaced; code that
  extended the old class's internals will need to target the new layers.

### Notes on output parity

- The v1 legacy array is reproduced exactly, including a v1 quirk: the `IHDR`
  group omits the `Interlace` key (v1 read 6 of the 7 IHDR fields). The interlace
  method is available on the typed core via `$document->header?->interlaceMethod`.

[2.0.0]: https://github.com/joserick/PNGMetadata/compare/v1.0.0...v2.0.0
