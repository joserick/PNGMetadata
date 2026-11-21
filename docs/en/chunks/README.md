---
title: Chunks and metadata
lang: en-US
---

# Chunks and metadata

This section is the heart of the documentation: **each chunk type, what it means, and how it is extracted**.

## General chunk table

| Chunk | Meaning | Supported by PNGMetadata v2? | Page |
|---|---|---|---|
| `IHDR` | Header: dimensions, color type | ✅ | [IHDR](/en/chunks/ihdr.html) |
| `tEXt` | Simple text (Latin-1) | ✅ | [Text](/en/chunks/text.html) |
| `zTXt` | Compressed text | ✅ | [Text](/en/chunks/text.html) |
| `iTXt` | International text (UTF-8) | ✅ | [Text](/en/chunks/text.html) |
| `eXIf` | EXIF data (camera) | ✅ | [EXIF](/en/chunks/exif.html) |
| XMP (in `iTXt`) | Structured metadata | ✅ | [XMP](/en/chunks/xmp.html) |
| `sRGB` | Rendering intent | ✅ | [Color](/en/chunks/color.html) |
| `iCCP` | ICC color profile | ✅ | [Color](/en/chunks/color.html) |
| `gAMA` | Gamma value | ✅ | [Color](/en/chunks/color.html) |
| `cHRM` | Primary chromaticities | ✅ | [Color](/en/chunks/color.html) |
| `cICP` | HDR color signaling | ⏳ Phase 3 | [PNG 3.0 / HDR](/en/chunks/png-3-hdr-apng.html) |
| `mDCV` | Display color volume | ⏳ Phase 3 | [PNG 3.0 / HDR](/en/chunks/png-3-hdr-apng.html) |
| `cLLI` | Content light levels | ⏳ Phase 3 | [PNG 3.0 / HDR](/en/chunks/png-3-hdr-apng.html) |
| `bKGD` | Background color | ✅ | [Background & transparency](/en/chunks/background-transparency.html) |
| `tRNS` | Transparency | ⏳ Phase 2 | [Background & transparency](/en/chunks/background-transparency.html) |
| `tIME` | Modification time | ⏳ Phase 2 | [Miscellaneous](/en/chunks/miscellaneous.html) |
| `pHYs` | Physical dimensions (DPI) | ⏳ Phase 2 | [Miscellaneous](/en/chunks/miscellaneous.html) |
| `sBIT` | Significant bits | ⏳ Phase 2 | [Miscellaneous](/en/chunks/miscellaneous.html) |
| `PLTE` | Color palette | ⏳ Phase 2 | [Miscellaneous](/en/chunks/miscellaneous.html) |
| `acTL`/`fcTL`/`fdAT` | Animation (APNG) | ⏳ Phase 3 | [PNG 3.0 / HDR](/en/chunks/png-3-hdr-apng.html) |

**Legend:** ✅ supported now · ⏳ planned (see [roadmap](/en/glossary.html#roadmap)).

## The metadata families

Chunks group into families by the type of information:

```
PNG metadata
├── Descriptive (human-readable text)
│   ├── tEXt  → title, author, copyright...
│   ├── zTXt  → the same but compressed
│   └── iTXt  → international text + XMP
├── Camera (EXIF)
│   └── eXIf  → aperture, shutter speed, ISO, date...
├── Structured (XML)
│   └── XMP (inside iTXt) → Dublin Core, Photoshop...
├── Color
│   ├── sRGB, iCCP, gAMA, cHRM  (SDR color)
│   └── cICP, mDCV, cLLI        (HDR color, PNG 3.0)
├── Image
│   ├── IHDR  → dimensions and format
│   ├── bKGD  → background color
│   ├── tRNS  → transparency
│   └── PLTE  → palette
└── Utility
    ├── tIME  → date
    ├── pHYs  → DPI
    └── sBIT  → color precision
```

## How to read the chunk pages

Each chunk page follows the same structure:

1. **What it is** — the concept in plain language.
2. **Binary structure** — a table with the fields and their bytes.
3. **How it is extracted** — the technical explanation and which decoder does it.
4. **Example** — a real case with the bytes.

Start with [IHDR](/en/chunks/ihdr.html), the chunk that defines how to read all the others.
