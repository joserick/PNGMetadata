---
title: Public API
lang: en-US
---

# Public API: the typed model

PNGMetadata v2 exposes **one** interface: `PngMetadataReader` produces an immutable, typed `MetadataDocument`. Why that is the only surface — and why there was no deprecation release — is [ADR-009](/en/decisions/ADR-009-v1-compatibility-removed-outright.html).

```php
use PNGMetadata\PngMetadataReader;

$document = PngMetadataReader::create()->read('photo.png');

$document->header?->width;              // int
$document->header?->colorType;          // ColorType enum
$document->header?->interlaceMethod;    // InterlaceMethod enum

$document->renderingIntent?->label();   // 'Perceptual', ...
$document->texts;                       // TextEntry[] (tEXt + zTXt + iTXt)
$document->xmp;                         // structured array
$document->exif;                        // decoded EXIF sections
$document->background;                  // ?BackgroundColor
$document->thumbnailBytes();            // ?string (no GD needed)
$document->toArray();                   // plain array (typed structure)
```

## Flexible inputs

The reader accepts **a path, a binary string, or a stream**:

```php
PngMetadataReader::create()->read('/path/photo.png');
PngMetadataReader::create()->read($binaryContent);
PngMetadataReader::create()->read($streamResource);
```

This is key for integrating with frameworks that deliver streams (e.g. Laravel's disk).

## Strict mode

```php
$document = PngMetadataReader::create(strict: true)->read('photo.png');
```

Validates each chunk's CRC-32 and ordering rules (IHDR first, IEND last), plus decode conformance: malformed chunks, duplicated singletons, and eXIf placed after the first IDAT raise a typed exception naming the chunk and its offset. Useful for conformance, but slower.

## Exceptions

The error contract is the typed hierarchy under `PNGMetadata\Exception\PngMetadataException`:

- `InvalidPngException` — the input is not a PNG or is not readable.
- `TruncatedDataException` — the file is incomplete.
- `ChunkDecodingException` — a chunk could not be decoded.
- `CrcMismatchException` — wrong CRC (strict mode only).

The taxonomy exists instead of v1's numeric codes for the reasons in [ADR-006](/en/decisions/ADR-006-typed-exception-taxonomy.html).

## Where is the v1 facade?

The old v1 array facade — object iteration, colon-separated string paths, null-on-failure extraction, GD-rendered thumbnails, two-column string output, and numeric error codes — was **removed in 2.0.0**: v2 is a breaking release. Every v1 entry point has a typed equivalent or a short recipe in the [README migration guide](https://github.com/joserick/PNGMetadata#upgrading-from-v1), and the `1.x` branch keeps the old API for legacy consumers. The byte-not-resource thumbnail is itself a recorded decision: [ADR-005](/en/decisions/ADR-005-no-gd-thumbnails-as-bytes.html).

## Next

Go back to [architecture](/en/architecture/architecture.html) or read about [PNG and its chunks](/en/introduction/what-is-png.html).
