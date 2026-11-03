---
title: What is a PNG?
lang: en-US
---

# What is a PNG?

**PNG** (*Portable Network Graphics*) is an image format designed in 1995 as a free, patent-free alternative to GIF. The name is pronounced "ping".

Contrary to popular belief, PNG is much more than "an image". It is an extremely flexible **data container** that can store:

- The image itself (the pixels).
- **Metadata**: descriptive text, camera data (EXIF), structured data (XMP).
- **Color information**: color space, gamma, ICC profile.
- **Physical information**: size in centimeters, DPI.
- **Animations** (APNG) and **HDR** (since PNG 3.0, 2025).

PNGMetadata only cares about metadata: the chunks that are *not* the image.

## Why does this library exist?

Most metadata-reading tools depend on an external program like **ExifTool** (a binary executed separately). This has two drawbacks:

1. It requires installing extra software on the server.
2. It adds a fragile external dependency to your application.

PNGMetadata solves this by being **100% native**: it reads the file byte by byte with pure PHP, with no external processes.

## The central idea: chunks

The keyword of PNG is **chunk**. A PNG file is a list of chunks, each with:

- A 4-letter **label** that says what it contains (e.g. `IHDR`, `eXIf`, `tEXt`).
- A **data block** of bytes.

Think of it as a train of wagons:

```
┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐
│  IHDR   │──│  tEXt   │──│  eXIf   │──│  IDAT   │──│  IEND   │
│ (header)│  │ (title) │  │ (EXIF)  │  │ (pixels)│  │ (end)   │
└─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘
```

A PNG reader **does not need to understand every wagon**: unknown ones are skipped safely (each wagon declares its length). This is what makes the format so extensible — which is why, 30 years later, new chunks (HDR in 2025) are still being added without breaking compatibility.

::: tip Analogy
PNGMetadata is like a train inspector that opens only certain wagons (the metadata ones), reads their contents, and lets the rest pass without looking inside.
:::

## The specification

PNG is governed by a **W3C** specification. The current version is the **Third Edition** (Recommendation of June 24, 2025), which adds official support for HDR and animations.

- [PNG 3.0 specification (W3C)](https://www.w3.org/TR/png-3/)

When in doubt about a chunk's meaning, the specification is the source of truth.

## Next

Now you know *what* a PNG is. Let's see *how* its data is organized physically: [The binary format](/en/introduction/binary-format.html).
