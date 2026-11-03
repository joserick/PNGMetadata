---
title: eXIf — EXIF data
lang: en-US
---

# eXIf — camera EXIF data

The `eXIf` chunk stores **EXIF** metadata: the information the camera recorded when taking the photo. Aperture, shutter speed, ISO, model, date, and even the thumbnail.

::: tip A bit of history
The `eXIf` chunk is relatively new (added in 2017). Before that, EXIF traveled hidden inside `tEXt` chunks with non-standardized keywords. That is why v1 of the library had `extractTExif` *and* `extractExif`. v2 unifies everything in `ExifDecoder`.
:::

## What is EXIF?

**EXIF** (*Exchangeable Image File Format*) is a standard (CIPA DC-008) that defines how to store technical metadata inside image files. In JPEG, EXIF goes in an `APP1` segment. In PNG, it goes inside the `eXIf` chunk.

## Binary structure

The `eXIf` chunk content is an **EXIF profile without the JPEG header**:

```
In JPEG:  [APP1 marker] [length] [ "Exif\0\0" ] [ TIFF data ]
In PNG:   (everything above is omitted)        [ TIFF data ]
```

The TIFF data starts with a **byte-order header**:

| Bytes | Meaning |
|---|---|
| `49 49 2A 00` | `II*\0` — *little-endian* (Intel) |
| `4D 4D 00 2A` | `MM\0*` — *big-endian* (Motorola) |

### Magic-byte validation

::: tip Design decision
Before decoding, the library verifies these 4 bytes. If they do not match, it **silently omits the EXIF** (no error) and continues with the rest of the metadata. This follows the specification's recommendation (§11.3.4.5.2) and avoids a `TypeError` that existed in v1 when `exif_read_data()` returned `false`.
:::

## How it is extracted

`ExifDecoder`:

1. Reads the chunk payload.
2. Verifies the magic bytes (`II*\0` or `MM\0*`).
3. Writes the payload to an in-memory stream (`php://temp`).
4. Calls `exif_read_data($stream, null, true)` — PHP parses it for you.
5. If it returns `false` or an empty array, the EXIF is omitted.

```php
// Usage
$document->exif; // array of decoded EXIF sections
```

### What `exif_read_data` returns

PHP's `exif` extension returns EXIF sections grouped:

```php
[
    'FILE'    => ['FileName' => ..., 'MimeType' => 'image/png', ...],
    'COMPUTED' => ['Width' => ..., 'Height' => ..., ...],
    'IFD0'    => ['Make' => 'SONY', 'Model' => ..., ...],
    'EXIF'    => ['DateTimeOriginal' => ..., 'ISOSpeedRatings' => ..., ...],
]
```

## Special rules for eXIf

- **Only one `eXIf` per file.** If there are several, the first one wins.
- EXIF may contain an embedded **thumbnail** (more common in JPEG than PNG). The library exposes it as bytes (`thumbnailBytes()`) without needing GD.
- Per the spec, EXIF is considered **historical information**: if the image was edited, it may no longer be accurate. Conflicts between EXIF and other chunks are left to the decoder's discretion.

## Metadata precedence

The same date can appear in three places: EXIF (`eXIf`), XMP (`exif:` namespace) and text (`tEXt`). PNGMetadata's documented policy:

```
decoded eXIf  >  legacy tEXt keywords
```

Both sources remain accessible separately; only the combined view prioritizes EXIF.

## Next

EXIF is "flat" metadata (key → value). XMP is **structured** metadata in XML: [XMP](/en/chunks/xmp.html).
