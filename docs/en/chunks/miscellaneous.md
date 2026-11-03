---
title: Miscellaneous chunks — tIME, pHYs, sBIT, PLTE
lang: en-US
---

# Miscellaneous chunks: tIME, pHYs, sBIT, PLTE

These chunks provide practical information: modification date, physical size (DPI), color precision, and the palette.

::: info Status
All chunks on this page are planned for Phase 2. They are documented here to complete the picture.
:::

## tIME — modification date

```
Bytes:  [year (2)] [month (1)] [day (1)] [hour (1)] [minute (1)] [second (1)]
```

7 bytes encoding the date in **UTC**. Example:

```
Bytes:  07 E6 | 08 | 0B | 0A | 00 | 00
        2026    08   11   10   00   00
        → 2026-08-11 10:00:00 UTC
```

Extracting it is trivial: unpack and build a `DateTimeImmutable`. PNGMetadata will map it to `$document->lastModified`.

## pHYs — physical dimensions (DPI)

```
Bytes:  [pixels per unit X (4)] [pixels per unit Y (4)] [unit (1)]
```

Defines the physical resolution of the image:

- **unit = 0**: the values are *unknown* (aspect ratio only).
- **unit = 1**: the values are pixels **per meter**.

To convert to DPI (dots per inch):

```
DPI = pixels per meter × 0.0254
```

Example: `2835` pixels per meter (the typical 72 DPI value):

```
2835 × 0.0254 = 72.009 ≈ 72 DPI
```

## sBIT — significant bits

```
Bytes:  1 to 4 bytes, depending on the color type (one per channel)
```

Indicates how many bits of each sample are actually **significant** (not noise). Used when an 8-bit image was generated from a lower-precision source (e.g. 5-bit).

## PLTE — color palette

```
Bytes:  up to 768 (256 entries × 3 RGB bytes)
```

Defines the palette of color-type-3 (indexed) images. Each entry is an 8-bit RGB triplet. PNGMetadata will expose it as a list of colors.

::: tip Why does the palette matter for metadata?
An indexed image only stores indices; without the palette you cannot know what color each index represents. It is structural information, not "metadata" strictly speaking, but useful for any tool that processes the image.
:::

## Next

We reach the frontier of the format: the **PNG 3.0** chunks for HDR and animation: [PNG 3.0 / HDR](/en/chunks/png-3-hdr-apng.html).
