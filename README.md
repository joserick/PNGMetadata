Welcome to PNGMetadata!
=======================

PNGMetadata is a library which is able to extract the metadata of an image in png format.

It is important to mention that PNGMetadata does **not** use the software ExifTool so it is completely native.

![PNGMetadata.gif](https://joserick.com/docs/pngmetadata/PNGMetadata.gif)
## Enjoying this package? [!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/joserick)

## What's new in v2

Version 2 is a ground-up rewrite on a layered architecture with a **typed, immutable API**. v2 is a **breaking release**: the v1 `PNGMetadata` array facade is gone and the typed core is now the only interface. The [1.x line](https://github.com/joserick/PNGMetadata) stays available (security fixes only) for anyone who needs the old API. See [Upgrading from v1](#upgrading-from-v1) for the migration map.

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

Use the package manager [Composer](https://getcomposer.org/) to install PNGMetadata.

```bash
$ composer require joserick/png-metadata
```

## Use

### Reading a PNG

```php
// include composer autoload
require 'vendor/autoload.php';

use PNGMetadata\PngMetadataReader;

// return an immutable MetadataDocument, or throw a typed exception
$document = PngMetadataReader::create()->read('Photo.png');
```

Access the decoded metadata through typed properties:

```php
$document->header?->width;              // int
$document->header?->colorType;          // ColorType enum
$document->header?->interlaceMethod;    // InterlaceMethod enum
$document->renderingIntent?->label();   // 'Perceptual', ...
$document->texts;                       // TextEntry[] (tEXt + iTXt, file order)
$document->xmp;                         // ?array structured XMP
$document->exif;                        // ?array decoded EXIF sections
$document->background;                  // ?BackgroundColor
$document->thumbnailBytes();            // ?string raw EXIF thumbnail, no GD
```

Or serialize the whole document:

```php
$array = $document->toArray(); // return simple 'Array'
```

### Flexible inputs

The reader accepts a file path, a binary string, or an open stream resource — useful for framework integrations (e.g. Laravel disks):

```php
$document = PngMetadataReader::create()->read($binaryString);
$document = PngMetadataReader::create()->read($streamResource);
```

### Strict mode

Enable strict validation — per-chunk CRC-32, ordering rules, and decode
conformance (malformed chunks, duplicated singletons, late `eXIf` placement) —
when you need spec-compliance checking:

```php
$document = PngMetadataReader::create(strict: true)->read('Photo.png');
```

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
$document = PngMetadataReader::create()->read('Photo.png');

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

### Is PNG?

Just read it — non-PNG input throws a typed domain exception:

```php
use PNGMetadata\Exception\PngMetadataException;

try {
    PngMetadataReader::create()->read('Photo.jpg');
    echo 'Yes, it is a PNG.';
} catch (PngMetadataException) {
    echo 'No, it is not a PNG.';
}
```

Or check without parsing anything, with the native function:

```php
\exif_imagetype('Photo.png') === IMAGETYPE_PNG; // bool
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
| `PNGMetadata::isPNG($path)` | `read()` + `catch`, or `\exif_imagetype($path) === IMAGETYPE_PNG` |
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
composer test              # unit suite (fast; excludes the `performance` group)
composer coverage          # unit suite + pcov: text summary and coverage.xml
php tools/check-coverage.php           # coverage gate (baseline ratchet, fails below it)
composer test:performance  # memory-bound scenario: 50 MB IDAT iterated lazily
composer phpstan           # static analysis, level max

# Real-world corpus (opt-in; third-party images are never committed):
PNGSUITE_DIR=/path/to/pngsuite composer test:integration
```

Before releasing, run all lanes: `composer test && composer phpstan && composer coverage && php tools/check-coverage.php && composer test:performance`, plus the PngSuite integration pass when a local corpus is available.

## License

The GNU Public License (GPLv3). Please see [License File](https://github.com/joserick/PNGMetadata/blob/master/LICENSE) for more information.
