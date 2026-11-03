---
title: PNGMetadata
lang: en-US
---

# PNGMetadata

**PNGMetadata** is a native PHP library that extracts metadata from PNG images without relying on external tools like ExifTool. It reads the file at the byte level to interpret the information hidden inside the image: EXIF data, XMP, dimensions, color, text, and more.

This documentation is a **reference guide** for understanding both the *what* (the metadata) and the *how* (the low-level byte reading). It assumes no prior experience with binary formats.

## Who is it for?

- **Developers** using the library who want to understand what data they get.
- **Contributors** who want to touch the code without getting lost in binary complexity.
- Anyone **curious** about how information is stored inside a file.

## How to read this documentation

Follow the section order if you are new to the topic:

1. **[Introduction](/en/introduction/README.html)** — what PNG is and why its format matters.
2. **[Project architecture](/en/architecture/README.html)** — how the library is organized and its data flow.
3. **[Chunks and metadata](/en/chunks/README.html)** — the core: each chunk type and what it means.
4. **[Glossary](/en/glossary.html)** — quick reference of technical terms.

## The essentials in one minute

A PNG is **not an image** in the classic sense. It is a container of data organized in blocks called **chunks**:

```
┌──────────────────────────────────────────────┐
│  Signature (8 bytes)  "this is a PNG"        │
├──────────────────────────────────────────────┤
│  Chunk IHDR      dimensions and color type   │
│  Chunk tEXt      text (author, title...)     │
│  Chunk eXIf      camera EXIF data            │
│  Chunk iTXt      XMP (structured metadata)   │
│  Chunk IDAT      the compressed pixels       │
│  Chunk IEND      end of file                 │
└──────────────────────────────────────────────┘
```

Each chunk is an "envelope" with a label (its type, 4 letters) and a payload (bytes). The library **opens the envelopes it cares about** and **ignores the pixels** (IDAT), because it only needs metadata.

::: tip Key fact
PNGMetadata **reads** metadata. It does not modify the file and never decodes the image itself (the pixels).
:::

## Useful links

- [Project repository](https://github.com/joserick/PNGMetadata)
- [PNG specification (W3C, Third Edition)](https://www.w3.org/TR/png-3/) — the normative source of truth.
