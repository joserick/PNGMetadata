Welcome to PNGMetadata!
=======================

PNGMetadata is a library which is able to extract the metadata of an image in png format.

It is important to mention that PNGMetadata does **not** use the software ExifTool so it is completely native.

![PNGMetadata.gif](https://joserick.com/docs/pngmetadata/PNGMetadata.gif)
## Enjoying this package? [!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/joserick)

## What's new in v2

Version 2 is a ground-up rewrite on a layered architecture with a **typed, immutable API**. v2 is a **breaking release**: the v1 `PNGMetadata` array facade is gone and the typed core is now the only interface. The [1.x line](https://github.com/joserick/PNGMetadata) stays available (security fixes only) for anyone who needs the old API. See [Upgrading from v1](#upgrading-from-v1) for the migration map.

> **Status: `2.0.0-beta.2` pre-release.** v2 is available for testing but is
> not yet the default stable line. Install the beta explicitly (see
> [Installation](#installation)); the supported chunk set is `IHDR`, `tEXt`,
> `zTXt`, `iTXt`/XMP, `eXIf`, `sRGB` and `bKGD`. Phase 2 chunks (`iCCP`,
> `gAMA`, `cHRM`, `tRNS`, `tIME`, `pHYs`, `sBIT`, `PLTE`) and Phase 3
> (APNG/HDR signaling) are follow-ups. API changes are still possible before
> `2.0.0` stable; feedback goes through the `v2-beta` issue template/label.

- **PHP 8.3+** with typed, immutable value objects and enums.
- **Layered core**: binary parsing → chunk decoders → typed domain model, with dependencies pointing inward.
- **Flexible input**: file path, binary string, or stream resource.
- **Strict mode** with per-chunk CRC-32 and ordering validation.
- **Memory-safe on big images**: `IDAT`/`fdAT` payloads are skipped lazily; extraction cost is proportional to the metadata, not the pixels.
- **Bug fixes**: EXIF decode failures no longer fatal, positional iTXt parsing (compressed text, language tags), Latin-1 → UTF-8 for tEXt, EXIF magic-byte validation, truncated-file safety.
- **Zero optional extensions**: GD is gone entirely — the embedded thumbnail is returned as bytes.
- **PHPUnit + PHPStan level max** test suite.

See the [CHANGELOG](CHANGELOG.md) for the full list.

## Requirements

- PHP >= 8.3
- XML PHP Extension
- EXIF PHP Extension
- mbstring PHP Extension
- zlib PHP Extension

## Installation

v2 is currently distributed as a pre-release tag. Install it explicitly until
`2.0.0` stable is published:

```bash
$ composer require joserick/png-metadata:2.0.0-beta.2
```

The unversioned `composer require joserick/png-metadata` command continues to
install the v1 stable line from `master`.

## Use

### Reading a PNG

```php
// include composer autoload
require 'vendor/autoload.php';

use PNGMetadata\PngMetadataReader;

// return an immutable MetadataDocument, or throw a typed exception
$document = PngMetadataReader::create()->read('Photo.png');
```

The reader is **reusable**: it only holds configuration (e.g. strict mode), so create it
once and read as many images as needed:

```php
$reader = PngMetadataReader::create();

foreach (['front.png', 'back.png'] as $path) {
    $document = $reader->read($path);
    // each call returns an independent MetadataDocument
}

// strict mode applies to every read() made through that instance
$strictReader = PngMetadataReader::create(strict: true);
$checked = $strictReader->read('photo.png');
```

Access the decoded metadata through typed properties:

```php
$document->header?->width;              // int
$document->header?->colorType;          // ColorType enum
$document->header?->interlaceMethod;    // InterlaceMethod enum
$document->renderingIntent?->label();   // 'Perceptual', ...
$document->texts;                       // TextEntry[] (tEXt + zTXt + iTXt, file order)
$document->xmp;                         // ?array structured XMP
$document->exif;                        // ?array decoded EXIF sections
$document->background;                  // ?BackgroundColor
$document->thumbnailBytes();            // ?string raw EXIF thumbnail, no GD
```

Or serialize the whole document:

```php
$array = $document->toArray(); // plain array with the typed structure
```

`toArray()` returns a plain array; this representative example covers header, color,
background, EXIF, XMP and text metadata (the EXIF `FILE` and `COMPUTED` sections are
omitted for brevity):

```php
$array = [
    'IHDR' => [
        'ImageWidth' => 1024,
        'ImageHeight' => 768,
        'BitDepth' => 8,
        'ColorType' => 'Palette',
        'Compression' => 'Deflate/Inflate',
        'Filter' => 'Adaptive',
        'Interlace' => 'Adam7 Interlace',
    ],
    'sRGB' => 'Saturation',
    'bKGD' => '2',
    'exif' => [
        'IFD0' => [
            'Make' => 'SONY',
            'Model' => 'ILCE-7M3',
            'Software' => 'Adobe Photoshop 26.0',
            'DateTime' => '2026:11:10 18:42:11',
        ],
        'EXIF' => [
            'ExposureTime' => '1/250',
            'FNumber' => '28/10',
            'ISOSpeedRatings' => 100,
            'DateTimeOriginal' => '2026:11:10 18:42:11',
        ],
    ],
    'xmp' => [
        'dc' => [
            'title' => 'Sunset over the bay',
            'creator' => 'José Carreón',
            'description' => 'Golden hour over the Pacific.',
        ],
        'xmp' => [
            'CreatorTool' => 'Adobe Photoshop 26.0',
        ],
        'photoshop' => [
            'Headline' => 'Golden hour',
        ],
    ],
    'texts' => [
        [
            'keyword' => 'Title',
            'text' => 'Sunset over the bay',
            'chunk' => 'tEXt',
        ],
        [
            'keyword' => 'Author',
            'text' => 'José Carreón',
            'chunk' => 'tEXt',
        ],
        [
            'keyword' => 'Comment',
            'text' => 'Shot from the pier.',
            'chunk' => 'tEXt',
        ],
        [
            'keyword' => 'Comment',
            'text' => 'Edited with a warm tone curve.',
            'chunk' => 'tEXt',
        ],
        [
            'keyword' => 'Description',
            'text' => 'Una vista al atardecer',
            'language' => 'es',
            'translatedKeyword' => 'Descripción',
            'chunk' => 'iTXt',
        ],
        [
            'keyword' => 'Copyright',
            'text' => '© 2026 José Carreón',
            'language' => 'es',
            'translatedKeyword' => 'Derechos de autor',
            'compressed' => true,
            'chunk' => 'iTXt',
        ],
    ],
];
```

`texts` groups `tEXt` and `iTXt` entries in file order, so repeated keywords are preserved
and iTXt entries keep their `language`, `translatedKeyword` and `compressed` fields;
`IHDR`, `sRGB` and `bKGD` are single-instance chunks and therefore appear as top-level keys.

### Flexible inputs

The reader accepts a file path, a binary string, or an open stream resource — useful for framework integrations (e.g. Laravel disks):

```php
$document = $reader->read($binaryString);
$document = $reader->read($streamResource);
```

### Strict mode

Enable strict validation — per-chunk CRC-32, ordering rules, and decode
conformance (malformed chunks, duplicated singletons, late `eXIf` placement) —
when you need spec-compliance checking:

```php
$document = PngMetadataReader::create(strict: true)->read('Photo.png');
```

### Compressed text budget

`zTXt` and compressed `iTXt` payloads are inflated with a **per-read budget**
of 16 MiB by default. The budget is cumulative across every compressed text
chunk in one `read()`, so a file cannot multiply memory use with many small
compressed chunks. A chunk that cannot be inflated within the remaining budget
follows the normal lenient/strict policy.

Consumers with legitimately larger metadata can raise the limit explicitly:

```php
$reader = PngMetadataReader::create(maxInflatedBytes: 32 * 1024 * 1024);
$document = $reader->read('Photo.png');
```

The value must be greater than zero; `0` and negative values are rejected at
construction.

## Examples

### Example 1 - All

Print all the metadata as a two-column list:

```php
$document = PngMetadataReader::create()->read('Photo.png');

$rows = [];
$flatten = function (array $data, string $prefix = '') use (&$flatten, &$rows): void {
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . ':' . $key;
        if (\is_array($value)) {
            $flatten($value, $path);
        } else {
            $rows[$path] = \is_scalar($value) ? (string) $value : \get_debug_type($value);
        }
    }
};
$flatten($document->toArray());

foreach ($rows as $path => $value) {
    \printf("%-44s %s\n", $path, $value);
}
```

Out:

| Metadata | Value |
|--|--|
| IHDR:ImageWidth | 800 |
| sRGB | Perceptual |
| texts:0:keyword | Title |
| ... | ... |

### Example 2 - Get a specific value

```php
$document = $reader->read('Photo.png');

echo $document->header?->width;                       // 800
echo $document->exif['IFD0']['Make'] ?? '';           // SONY
```

### Example 3 - Metadata types

Iterate the top-level groups of the document:

```php
foreach ($document->toArray() as $group => $value) {
    echo $group . "\n"; // IHDR, sRGB, bKGD, exif, xmp, texts...
}
```

## Extras Functions

### Get the thumbnail stored in the metadata!

The embedded EXIF thumbnail is available as raw bytes — no GD required:

```php
$bytes = $document->thumbnailBytes(); // ?string

if ($bytes !== null) {
    header('Content-Type: ' . ($document->exif['THUMBNAIL']['MimeType'] ?? 'image/jpeg'));
    echo $bytes;
}
```

If you genuinely need an image resource, decode it yourself (this is where GD re-enters, as a consumer choice):

```php
$image = \imagecreatefromstring($document->thumbnailBytes() ?? ''); // needs ext-gd
```

## Upgrading from v1

**v2 removes the `PNGMetadata` facade (breaking).** Every v1 entry point has a typed equivalent or a small recipe:

| v1 | v2 |
|--|--|
| `new PNGMetadata($path)` | `PngMetadataReader::create()->read($path)` |
| `PNGMetadata::extract($path)` (null on failure) | `try/catch` recipe below |
| `$png->toArray()` (v1 groups, `ksort`ed) | `$document->toArray()` (typed structure) |
| `$png->get('exif:DateTime')` | `$document->exif['IFD0']['DateTime'] ?? null` |
| `foreach ($png as $key => $value)` | iterate `$document->toArray()` |
| `echo $png` (two-column table) | Example 1 flatten snippet |
| `$png->getThumbnail()` (`\GdImage`) | `$document->thumbnailBytes()` (`?string`) |
| `PNGMetadata::getType($path)` | `\exif_imagetype($path)` |
| `InvalidArgumentException` codes 101/102/103 | typed exceptions under `PNGMetadata\Exception\PngMetadataException` |

Recipe — v1's never-throwing `extract()`:

```php
use PNGMetadata\Exception\PngMetadataException;
use PNGMetadata\Metadata\MetadataDocument;

$document = null;
try {
    $document = PngMetadataReader::create()->read($path);
} catch (PngMetadataException) {
    // null, like PNGMetadata::extract()
}
```

Behavior notes beyond the API shape:

- Output no longer reproduces v1 quirks: the IHDR group now **includes** the interlace method (`header.interlaceMethod`); v1 silently omitted it.
- tEXt keyword/text are UTF-8 (converted from the spec's Latin-1), repeated keywords are preserved in file order, and a corrupt EXIF/XMP section is omitted without losing the rest of the metadata.
- Requirements changed: PHP 8.3+; no GD at all.

## Development

```bash
composer test                 # unit suite (fast; excludes the `performance` group)
composer coverage             # unit suite + pcov: text summary and coverage.xml
php tools/check-coverage.php  # coverage gate (baseline ratchet, fails below it)
composer test:performance     # memory-bound scenario: 50 MB IDAT iterated lazily
composer phpstan              # static analysis, level max

# Real-world corpus (opt-in; third-party images are never committed):
PNGSUITE_DIR=/path/to/pngsuite composer test:integration
```

Before releasing, run all lanes: `composer test && composer phpstan && composer coverage && php tools/check-coverage.php && composer test:performance`, plus the PngSuite integration pass when a local corpus is available.

## License

The GNU Public License (GPLv3). Please see [License File](https://github.com/joserick/PNGMetadata/blob/master/LICENSE) for more information.
