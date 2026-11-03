Welcome to PNGMetadata!
=======================

PNGMetadata is a library which is able to extract the metadata of an image in png format.

It is important to mention that PNGMetadata does **not** use the software ExifTool so it is completely native.

![PNGMetadata.gif](https://joserick.com/docs/pngmetadata/PNGMetadata.gif)
## Enjoying this package? [!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/joserick)

## What's new in v2

Version 2 is a ground-up rewrite on a layered architecture, while **keeping the v1 public API intact** — the examples below work unchanged on v2.

- **PHP 8.3+** with typed, immutable value objects and enums.
- **Layered core**: binary parsing → chunk decoders → typed domain model → v1-compatible facade.
- **Bug fixes**: EXIF decode failures no longer fatal, positional iTXt parsing (compressed text, language tags), Latin-1 → UTF-8 for tEXt, EXIF magic-byte validation, truncated-file safety.
- **GD is now optional** — only `getThumbnail()` needs it.
- **PHPUnit + PHPStan level max** test suite.

See the [CHANGELOG](CHANGELOG.md) for the full list and the [upgrade notes](#upgrading-from-v1).

## Requirements

- PHP >= 8.3
- XML PHP Extension
- EXIF PHP Extension
- mbstring PHP Extension
- zlib PHP Extension
- GD PHP Extension *(optional — only for `getThumbnail()`)*

## Installation

Use the package manager [Composer](https://getcomposer.org/) to install PNGMetadata.

```bash
$ composer require joserick/png-metadata
```

## Use

### Getting a PNGMetadata Instance

```php
// include composer autoload
require 'vendor/autoload.php';

// import the Joserick PNGMetadata
use PNGMetadata\PNGMetadata;

// build PNGMetadata object with a image path.
$png_metadata = new PNGMetadata('Photo.png'); // return a 'ArrayObject' or 'Exception'
```

In case you don't want to see any **errors** (Exception) generated you can used the static function '**extract()**' which returns a 'null' in case of error.

```php
$png_metadata = PNGMetadata::extract('Photo.png'); // return a 'ArrayObject' or 'null'
```

If you want work with simple array without any other function you can call to '**toArray()**'.

```php
$png_metadata = new PNGMetadata('Photo.png');
$metadata_array = $png_metadata->toArray(); // return simple 'Array'
//or
$metadata_array = PNGMetadata::extract('Photo.png')->toArray(); // return simple 'Array'
```

## Examples

### Example 1 - All

Print all the metadata.

```php
$png_metadata = new PNGMetadata('../Photo.png');
echo $png_metadata; // Print metadata in 2 colums.
```

Out:

| Metadata | Value |
|--|--|
| exif:DateTime | 2019:09:08 23:01:23 |
| exif:Make | SONY |
| exif:MimeType | image/png |
| ... | ... |

### Example 2 - Get Metadata

Get specific metadata.

```php
$png_metadata = new PNGMetadata(__DIR__.'/Photo.png');
echo $png_metadata->get('exif:DateTime'); // Return a value, a array or null.
```

### Example 3 - Types

Print the metadata types (IHDR, sRGB, BKGD, EXIF, XMP, CRS, DATE, DC, ICC, AUX, ...).

```php
$png_metadata = new PNGMetadata('./Path/Photo.png');
// or
$png_metadata = PNGMetadata::extract('./Path/Photo.png');

foreach($png_metadata as $key => $value){
	echo $key . "<br>"; // Metadata types
}
```

## New in v2: the typed core

Beyond the v1 facade, v2 exposes a typed, immutable domain model. Use it when you want structured data instead of the legacy array:

```php
use PNGMetadata\PngMetadataReader;

$document = PngMetadataReader::create()->read('Photo.png');

$document->header?->width;              // int
$document->header?->colorType;          // ColorType enum
$document->renderingIntent?->label();   // 'Perceptual', ...
$document->texts;                       // TextEntry[] (tEXt + iTXt)
$document->xmp;                         // structured XMP array
$document->exif;                        // decoded EXIF sections
$document->thumbnailBytes();            // ?string (no GD needed)
```

The core also accepts a binary string or a stream resource, not only a file path — useful for framework integrations:

```php
$document = PngMetadataReader::create()->read($binaryString);
$document = PngMetadataReader::create()->read($streamResource);
```

Enable strict validation (CRC-32 per chunk + ordering rules) when you need conformance checking:

```php
$document = PngMetadataReader::create(strict: true)->read('Photo.png');
```

## Extras Functions

### Get the thumbnail stored in the metadata!
```php
$png_metadata = new PNGMetadata('../Photo.png');
// or
$png_metadata = PNGMetadata::extract('../Photo.png');

$thumb = $png_metadata->getThumbnail();

if ($thumb !== false) {
	header('Content-Type: image/png');
	imagepng($thumb);
	imagedestroy($thumb);
}
```

### Is PNG?
```php
if (PNGMetadata::isPNG('./Photo_jpg.png')){
	echo 'Yes, it is a PNG.';
}else{
	echo 'No, it is not a PNG.'
}
```

### What type?
|            |            |          |         |          |         |
|:----------:|:----------:|:--------:|:-------:|:--------:|:-------:|
|   GIF(1)   |   JPEG(2)  |  PNG (3) |  SWF(4) |  PSD(5)  |  BMP(6) |
| TIFF_II(7) | TIFF_MM(8) | JP2 (10) | JPX(11) |  JB2(12) | SWC(13) |
|   IFF(14)  |  WBMP(15)  |  XBM(16) | ICO(17) | WEBP(18) |         |

```php
return PNGMetadata::getType('./Photo') == 1 // GIF?
```

## Upgrading from v1

v2 preserves the v1 public API. If you only used the documented methods, no code changes are needed:

- `new PNGMetadata($path)`, `PNGMetadata::extract($path)`, `->toArray()`, `->get('a:b')`, `echo $metadata`, `->getThumbnail()`, `PNGMetadata::isPNG($path)`, `PNGMetadata::getType($path)`.

One intentional output difference: v1 omitted the `Interlace` key from the `IHDR` group (it read 6 of the 7 fields). v2's legacy array **reproduces that key set exactly** for parity; the interlace method is available on the typed core (`$document->header?->interlaceMethod`).

Requirements changed: PHP 8.3+ (was 7.4+), and `ext-gd` is now only needed for `getThumbnail()`.

## License

The GNU Public License (GPLv3). Please see [License File](https://github.com/joserick/PNGMetadata/blob/master/LICENSE) for more information.
