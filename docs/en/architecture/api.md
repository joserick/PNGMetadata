---
title: Public API
lang: en-US
---

# Public API: two ways to use the library

PNGMetadata v2 offers **two coexisting interfaces**:

1. The **v1 API** (the `PNGMetadata` facade) — an `ArrayObject` with a nested array, designed for compatibility with the original version.
2. The **v2 API** (`PngMetadataReader`) — a typed, immutable model, more powerful.

Choose based on what you need: compatibility (v1) or strict typing (v2).

## v1 API — the classic facade

The simplest form, used by v1 consumers unchanged.

```php
use PNGMetadata\PNGMetadata;

$png = new PNGMetadata('photo.png');

// Colon-path access
$date  = $png->get('exif:DateTime');   // '2019:09:08 23:01:23'
$width = $png->get('IHDR:ImageWidth'); // 800

// The whole array
$all = $png->toArray();

// Print in two columns
echo $png;

// Embedded thumbnail (needs ext-gd)
$thumb = $png->getThumbnail(); // \GdImage|false
```

### Static methods

```php
PNGMetadata::extract('photo.png'); // ?PNGMetadata (null on failure, never throws)
PNGMetadata::isPNG('photo.png');   // bool
PNGMetadata::getType('photo.png'); // int|false (IMAGETYPE_PNG, IMAGETYPE_GIF, ...)
```

### The v1 array shape

The `toArray()` array groups by metadata type and is ordered with `ksort()`:

```php
[
    'IHDR' => [
        'ImageWidth'  => 800,
        'ImageHeight' => 600,
        'BitDepth'    => 8,
        'ColorType'   => 'RGB with Alpha',
        'Compression' => 'Deflate/Inflate',
        'Filter'      => 'Adaptive',
        // Note: "Interlace" is intentionally omitted (v1 quirk)
    ],
    'bKGD'  => '100 200 300',
    'exif'  => [ 'DateTime' => '...', 'Make' => 'SONY', ... ],
    'sRGB'  => 'Perceptual',
]
```

::: warning Compatibility quirk
v1 read only 6 of the 7 IHDR fields, so **the v2 array reproduces that omission** of the `Interlace` key to avoid breaking consumers. The data is still available in the typed v2 API.
:::

## v2 API — the typed model

For new code, this is the recommended option: objects with types, enums, no ambiguous strings.

```php
use PNGMetadata\PngMetadataReader;

$document = PngMetadataReader::create()->read('photo.png');

$document->header?->width;              // int
$document->header?->colorType;          // ColorType enum
$document->header?->interlaceMethod;    // InterlaceMethod enum

$document->renderingIntent?->label();   // 'Perceptual', ...
$document->texts;                       // TextEntry[] (tEXt + iTXt)
$document->xmp;                         // structured array
$document->exif;                        // decoded EXIF sections
$document->thumbnailBytes();            // ?string (no GD needed)
```

### Flexible inputs

Unlike v1 (disk path only), the v2 API accepts **a path, a binary string, or a stream**:

```php
PngMetadataReader::create()->read('/path/photo.png');
PngMetadataReader::create()->read($binaryContent);
PngMetadataReader::create()->read($streamResource);
```

This is key for integrating with frameworks that deliver streams (e.g. Laravel's disk).

### Strict mode

```php
$document = PngMetadataReader::create(strict: true)->read('photo.png');
```

Validates each chunk's CRC-32 and ordering rules (IHDR first, IEND last). Useful for conformance, but slower.

## Comparison table

| Feature | v1 API (`PNGMetadata`) | v2 API (`PngMetadataReader`) |
|---|---|---|
| Return type | `ArrayObject` / array | `MetadataDocument` (immutable, typed) |
| Inputs | Disk path only | Path, string, stream |
| CRC validation | No | Yes (strict mode) |
| Errors | `extract()` → null | Typed domain exceptions |
| Thumbnail | `\GdImage` (needs GD) | bytes (no GD) |
| Recommended for | Compatibility / legacy code | New code |

## Exceptions

Both APIs share an exception hierarchy under `PNGMetadata\Exception\PngMetadataException`:

- `InvalidPngException` — the input is not a PNG or is not readable.
- `TruncatedDataException` — the file is incomplete.
- `ChunkDecodingException` — a chunk could not be decoded.
- `CrcMismatchException` — wrong CRC (strict mode only).

The v1 facade keeps the historical `InvalidArgumentException` codes (101 = empty path, 102 = missing, 103 = not PNG) to avoid breaking existing `catch` blocks.
