Welcome to PNGMetadata!
=======================

PNGMetadata is a library which is able to extract the metadata of an image in png format.

It is important to mention that PNGMetadata does **not** use the software ExifTool so it is completely native.

![PNGMetadata.gif](https://joserick.com/docs/pngmetadata/PNGMetadata.gif)

## Requirements

- PHP >= 7.4
- XML PHP Extension
- JSON PHP Extension
- EXIF PHP Extension

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

In case you don't want to see any **errors** (Exception) generated you can used the static function '**extract()**' which returns a 'false' in case of error.

```php
$png_metadata = PNGMetadata::extract('Photo.png'); // return a 'ArrayObject' or 'False'
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
$png_metadata = new PNGMetadata(___DIR___.'/Photo.png');
echo $png_metadata->get('exif:DateTime'); // Return a value, a array or false.
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

## License

The GNU Public License (GPLv3). Please see [License File](https://github.com/joserick/PNGMetadata/blob/master/LICENSE) for more information.
