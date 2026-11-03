---
title: IHDR — the header
lang: en-US
---

# IHDR — the image header

The `IHDR` (*Image Header*) chunk is the **first** of every PNG and the only critical chunk PNGMetadata decodes. It describes the pixel dimensions and format.

::: tip Why it is special
IHDR is "the context" for the rest of the chunks. Several ancillary chunks (like `bKGD`) can only be interpreted knowing the **color type** IHDR declares. That is why the library decodes it first and stores it in the context.
:::

## Binary structure

IHDR is exactly **13 bytes**, always:

| Field | Bytes | Type | Meaning |
|---|---|---|---|
| Width | 0–3 | uint32 | Width in pixels |
| Height | 4–7 | uint32 | Height in pixels |
| Bit depth | 8 | uint8 | Bits per color sample |
| Color type | 9 | uint8 | Color type (see below) |
| Compression | 10 | uint8 | Compression method (always 0) |
| Filter | 11 | uint8 | Filter method (always 0) |
| Interlace | 12 | uint8 | Interlacing (0 or 1) |

### Example: an 800×600 image

```
Bytes:  00 00 03 20 | 00 00 02 58 | 08 | 06 | 00 | 00 | 00
        └─ width ──┘ └─ height ──┘  └─┘ └─┘ └─┘ └─┘ └─┘
          800           600         BD  CT  C   F   I
```

Interpretation: width 800, height 600, bit depth 8, color type 6 (RGBA), standard compression, standard filter, not interlaced.

## The color type

Byte 9 encodes how the color channels are organized:

| Value | Enum (`ColorType`) | Label | Channels |
|---|---|---|---|
| 0 | `Grayscale` | Grayscale | 1 (gray) |
| 2 | `RGB` | RGB | 3 (red, green, blue) |
| 3 | `Palette` | Palette | 1 palette index |
| 4 | `GrayscaleWithAlpha` | Grayscale with Alpha | 2 (gray + alpha) |
| 6 | `RGBWithAlpha` | RGB with Alpha | 4 (RGBA) |

::: warning Cross-validation
Not every color-type + bit-depth combination is valid. For example, a palette (type 3) only accepts 1, 2, 4 or 8 bits per sample, never 16. `IhdrDecoder` validates this matrix and throws `ChunkDecodingException` for impossible combinations.
:::

### Valid depths per type

| Color type | Allowed depths |
|---|---|
| Grayscale (0) | 1, 2, 4, 8, 16 |
| RGB (2) | 8, 16 |
| Palette (3) | 1, 2, 4, 8 |
| Grayscale + Alpha (4) | 8, 16 |
| RGB + Alpha (6) | 8, 16 |

## How it is extracted in the code

The `IhdrDecoder` class:

1. Reads the 13 chunk bytes.
2. Unpacks them with `unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', ...)`.
3. Converts the color-type byte into a `ColorType` enum.
4. Validates the type + depth combination.
5. Stores the resulting `HeaderData` in the **context** (for dependent chunks) and in the builder.

```php
// Usage from the v2 API
$document = PngMetadataReader::create()->read('photo.png');

$document->header?->width;          // 800 (int)
$document->header?->height;         // 600 (int)
$document->header?->bitDepth;       // 8
$document->header?->colorType;      // ColorType::RGBWithAlpha
$document->header?->interlaceMethod; // InterlaceMethod::None
```

## Interlacing

Byte 12 accepts two values:

| Value | Enum | Meaning |
|---|---|---|
| 0 | `None` | Pixels stored in normal order |
| 1 | `Adam7` | Adam7 interlacing (7 progressive passes) |

For metadata extraction, interlacing **does not affect** the metadata: it is a property of how pixels are stored, which PNGMetadata ignores by design.

::: details v1 compatibility quirk
v1 read only 6 of the 7 IHDR fields (it forgot interlacing). The v2 legacy array reproduces that omission; the `interlaceMethod` enum is present in the typed v2 model.
:::

## Next

IHDR describes the image. The **text** chunks describe the context: [tEXt, zTXt and iTXt](/en/chunks/text.html).
