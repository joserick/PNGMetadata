---
title: Background & transparency — bKGD, tRNS
lang: en-US
---

# Background and transparency: bKGD and tRNS

These two chunks are interpreted **based on the color type** declared in IHDR. They are the perfect example of why IHDR is the "context" for the rest.

## bKGD — background color

The `bKGD` (*background*) chunk suggests a background color for when the image is displayed over a viewer without its own background (e.g. a viewer that paints gray).

### The structure depends on the color type

| Color type | Bytes | Content |
|---|---|---|
| Palette (3) | 1 | Palette index |
| Grayscale (0, 4) | 2 | Gray sample (uint16) |
| RGB (2, 6) | 6 | Three samples: R, G, B (uint16 each) |

Example with a truecolor image (color type 2):

```
Bytes:  00 00 | FF FF | 00 00
        └red─┘ └green┘ └blue─┘
        0       65535    0      → pure green
```

### Why the IHDR context matters

If you read 6 bytes without knowing the color type, you cannot distinguish "RGB" from "3 gray samples". The `BkgdDecoder` consults the `DecodingContext`:

```php
$header = $context->requireHeader(); // the already-decoded IHDR

match ($header->colorType) {
    ColorType::Palette  => /* 1 byte → index */,
    ColorType::Grayscale, ColorType::GrayscaleWithAlpha => /* 2 bytes → gray */,
    ColorType::RGB, ColorType::RGBWithAlpha => /* 6 bytes → RGB */,
};
```

```php
// Usage
$document->background; // BackgroundColor
$document->background?->toString(); // "65535 0 32768"
```

::: tip v1 did it "by eye"
v1 guessed the format with `strlen($chunk) < 2`. It worked for most cases, but conceptually mixed gray and RGB. v2 uses the explicit context.
:::

## tRNS — transparency

The `tRNS` (*transparency*) chunk allows defining transparency when the color type does **not** include an alpha channel (i.e. types 0, 2 and 3; it is forbidden in 4 and 6, which already have alpha).

| Color type | Content |
|---|---|
| Palette (3) | One alpha byte per palette entry |
| Grayscale (0) | One gray sample (2 bytes) that will be transparent |
| RGB (2) | One RGB sample (6 bytes) that will be transparent |

The idea: instead of storing a full alpha channel (which doubles the size), one color is designated as "transparent".

::: info Status
`tRNS` is supported since `2.0.0-beta.5`. `TrnsDecoder` uses the IHDR color type to choose the variant, rejects `tRNS` for alpha color types, and treats the payload length according to the same lenient/strict policy as `bKGD`.
:::

```php
$document->transparency;                  // ?Transparency
$document->transparency?->toArray();
```

## Next

We have covered the bulk of metadata. The **miscellaneous** chunks (date, DPI, palette) close the classic picture: [Miscellaneous](/en/chunks/miscellaneous.html).
