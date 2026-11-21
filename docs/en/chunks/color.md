---
title: Color chunks — sRGB, iCCP, gAMA, cHRM
lang: en-US
---

# Color chunks: sRGB, iCCP, gAMA, cHRM

These chunks describe **how the pixel colors should be interpreted**: what color space they use, what gamma they have, and what chromaticities (primaries) define their gamut.

::: warning Prerequisite
If you don't know what "gamma" or "primaries" are, go through the [glossary](/en/glossary.html) first. We assume those notions here.
:::

## Color precedence

PNG allows several color chunks to coexist, but **one wins**. The specification (§4.3, Table 1) defines a priority:

| Priority | Chunk | Description |
|---|---|---|
| 1 (highest) | `cICP` | Color signaling by code points (HDR, PNG 3.0) |
| 2 | `iCCP` | Full ICC profile |
| 3 | `sRGB` | Declares "this is standard sRGB" |
| 4 (lowest) | `cHRM` + `gAMA` | Explicit chromaticities + gamma |

> *"the chunk with the lowest Priority number should take precedence and any higher-numbered chunk types should be ignored."*

That is: if `cICP` is present, the others are ignored. Otherwise `iCCP` overrides `sRGB`, etc.

## sRGB — rendering intent

The `sRGB` chunk is the simplest: **1 single byte**.

```
Bytes:  [rendering intent]
```

The byte encodes the *rendering intent*, an ICC concept:

| Value | Enum (`RenderingIntent`) | Label | Meaning |
|---|---|---|---|
| 0 | `Perceptual` | Perceptual | Optimized for photos, "pleasing" colors |
| 1 | `RelativeColorimetric` | Relative Colorimetric | Exact colors relative to white |
| 2 | `Saturation` | Saturation | Optimized for graphics, vivid colors |
| 3 | `AbsoluteColorimetric` | Absolute Colorimetric | Absolute exact colors |

### How it is extracted

`SrgbDecoder` reads the byte and converts it to the `RenderingIntent` enum. If the value is out of range (4-255), it reports it as "unknown" instead of failing.

```php
$document->renderingIntent; // RenderingIntent::Perceptual
$document->renderingIntent?->label(); // 'Perceptual'
```

## iCCP — embedded ICC profile

```
profile name (Latin-1) \0 | compression method (1 byte) | ICC profile (zlib compressed)
```

An **ICC profile** (International Color Consortium) is a binary file that mathematically describes a color space. It is the most complete and flexible mechanism, but also the heaviest.

::: info Status
`iCCP` is supported since `2.0.0-beta.4`. `IccpDecoder` validates the profile name and method, inflates the profile under the reader's shared budget, then parses the 128-byte ICC header, the tag table and the `desc`/`mluc` description tag. The document exposes the profile name, description, version, class, color space, PCS and rendering intent.
:::

```php
$document->iccProfile; // ?IccProfile
$document->iccProfile?->description;
```

## gAMA — gamma

```
Bytes:  [4-byte integer = gamma × 100000]
```

The value is stored multiplied by 100000. For example, `45455` corresponds to a gamma of `0.45455` (which is `1/2.2`, the typical display gamma).

```
45455 ÷ 100000 = 0.45455
```

## cHRM — primary chromaticities

```
Bytes:  8 four-byte integers, each × 100000
        [white x] [white y] [red x] [red y] [green x] [green y] [blue x] [blue y]
```

Defines the chromatic coordinates (in CIE xyY space) of the white point and the red, green and blue primaries. Together with `gAMA`, it describes the color space exactly without needing an ICC profile.

::: info Status
`gAMA` and `cHRM` are supported since `2.0.0-beta.4`. `GamaDecoder` rejects a zero or non-4-byte payload; `ChrmDecoder` requires exactly 32 bytes and exposes the white point plus the red, green and blue primaries.
:::

```php
$document->gamma?->value();       // e.g. 0.45455
$document->chromaticities;        // ?Chromaticities
```

## What PNGMetadata v2 supports today

v2 supports **`sRGB`, `iCCP`, `gAMA` and `cHRM`**. The document exposes each decoded chunk separately and adds `colorSource` with the resolved winner for the supported chunks:

```
iCCP > sRGB > cHRM+gAMA
```

`gAMA` alone is visible as a gamma value but does not produce a `colorSource`, because it does not define a complete color space. `cICP` joins the top of this precedence table in Phase 3.

## Next

Color describes how the image looks. Let's see the **background and transparency** chunks: [Background & transparency](/en/chunks/background-transparency.html).
