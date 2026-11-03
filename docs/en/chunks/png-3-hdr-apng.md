---
title: PNG 3.0 — HDR and APNG
lang: en-US
---

# PNG 3.0: HDR (cICP, mDCV, cLLI) and APNG

The **Third Edition** of the PNG specification (W3C, June 2025) added official support for **HDR images** (high dynamic range) and **animations** (APNG).

::: info Status
Everything on this page is planned for **Phase 3**. It is PNGMetadata's future differentiator: almost no PHP library supports these chunks yet.
:::

## What is HDR?

**HDR** (*High Dynamic Range*) is an image format capable of representing a much larger brightness range than traditional SDR (5–8 *stops*). PNG supports two HDR transfer functions:

- **PQ** (Perceptual Quantizer, ST 2084) — an absolute, display-based system.
- **HLG** (Hybrid Log-Gamma) — a relative system, backwards compatible with SDR.

HDR needs to signal three things: which primaries it uses, which transfer function, and how bright the content is. That is what the three new chunks do.

## cICP — coding-independent code points

```
Bytes:  [color primaries (1)] [transfer function (1)] [matrix coefficients (1)] [full range flag (1)]
```

Uses *code points* from the **ITU-T H.273** standard to identify the color/video signal.

| Field | Meaning |
|---|---|
| Color primaries | The color-space primaries (e.g. 9 = BT.2020) |
| Transfer function | The transfer function (e.g. 16 = PQ, 18 = HLG) |
| Matrix coefficients | Color matrix — in PNG **always 0** (RGB) |
| Full range flag | 1 = full-range, 0 = narrow-range |

### Normative examples (hex bytes)

| Bytes | Meaning |
|---|---|
| `09 10 00 01` | **BT.2100 PQ** (HDR10), full-range |
| `09 12 00 01` | **BT.2100 HLG**, full-range |
| `01 01 00 00` | BT.709 narrow-range (video) |
| `0C 0D 00 01` | **Display P3**, full-range |

::: tip Key fact
`cICP` is the color chunk with the **highest precedence** (priority 1). If present, it overrides `iCCP`, `sRGB` and `gAMA`/`cHRM`.
:::

## mDCV — Mastering display color volume

```
Bytes:  24 in total
        [primaries R (x,y uint16)] [G (x,y)] [B (x,y)]  → 12 bytes, divisor 0.00002
        [white point (x,y uint16)]                        → 4 bytes, divisor 0.00002
        [max luminance (uint32)]                          → 4 bytes, divisor 0.0001 cd/m²
        [min luminance (uint32)]                          → 4 bytes, divisor 0.0001 cd/m²
```

Describes the **display used to master** the content (SMPTE ST 2086 standard). It lets the viewer's display optimize its *tone mapping*.

::: details Ordering curiosity
The spec orders the primaries as: "largest x first, then largest y, then the remaining one". For RGB, that coincides with R, G, B.
:::

## cLLI — Content light levels

```
Bytes:  [MaxCLL (uint32, ÷0.0001 cd/m²)] [MaxFALL (uint32, ÷0.0001 cd/m²)]
```

- **MaxCLL** (*Maximum Content Light Level*): the brightness of the brightest pixel.
- **MaxFALL** (*Maximum Frame-Average Light Level*): the average brightness of the brightest frame.

Normative example: `1000 cd/m²` is stored as `10000000` → bytes `00 98 96 80`.

### The HDR10 trio

```
cICP  +  mDCV  +  cLLI  =  complete HDR10 signaling
```

The three together describe exactly how an HDR image should look on a compatible display.

## APNG — animations

APNG (*Animated PNG*) is now an official part of the spec. It adds three chunks:

| Chunk | Content |
|---|---|
| `acTL` | Control: number of frames and plays (8 bytes) |
| `fcTL` | Per-frame control: dimensions, position, delay, disposal (26 bytes) |
| `fdAT` | Each frame's data (like IDAT but with a sequence number) |

PNGMetadata will be able to summarize the animation **without decoding pixels**: number of frames, total duration and loop, from `acTL` and the `fcTL` chunks.

::: tip Media type
PNG 3.0 also defines the media type **`image/apng`** to serve them correctly over HTTP.
:::

## Ordering rules

The `cICP` and `mDCV` chunks **must** appear before `PLTE` and `IDAT`. PNGMetadata's strict mode will validate these rules.

## Next

This concludes the chunk tour. Review the terms in the [Glossary](/en/glossary.html).
