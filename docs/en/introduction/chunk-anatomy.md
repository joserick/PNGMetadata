---
title: Anatomy of a chunk
lang: en-US
---

# Anatomy of a chunk

The chunk is the fundamental unit of the PNG format. Understanding its structure is understanding 90% of how metadata extraction works.

## The four parts of a chunk

Every chunk always has the same structure:

```
┌──────────────┬──────────────┬────────────────────┬──────────────┐
│  Length      │  Type        │  Data              │  CRC         │
│  (4 bytes)   │  (4 bytes)   │  (Length bytes)    │  (4 bytes)   │
└──────────────┴──────────────┴────────────────────┴──────────────┘
```

1. **Length** — a 4-byte integer (big-endian) stating how many bytes `Data` has. Maximum 2³¹−1.
2. **Type** — 4 ASCII letters identifying the chunk type (e.g. `IHDR`, `tEXt`, `eXIf`).
3. **Data** — the actual content, of variable length (can be 0).
4. **CRC** — a 4-byte *checksum* to detect transmission errors.

### Real example

The `tEXt` chunk with the text "Author: José" would look (simplified) like this:

```
Length = 0x0C (12)          → 00 00 00 0C
Type   = "tEXt"             → 74 45 58 74
Data   = "Author\0José"     → 41 75 74 68 6F 72 00 4A 6F 73 E9 ...
CRC    = (computed)         → XX XX XX XX
```

## The CRC: the integrity guard

The **CRC** (*Cyclic Redundancy Check*) is a number computed from `Type + Data`. If a single bit is corrupted during transmission, the recomputed CRC will not match the stored one.

PNGMetadata has **two modes**:

| Mode | Behavior | When to use it |
|---|---|---|
| **Lenient** (default) | Does not verify CRC | Normal use: faster, tolerates files with a bad CRC |
| **Strict** | Verifies CRC and chunk ordering | When you need to validate file integrity |

```php
// Strict mode: validates per-chunk CRC + ordering rules
$document = PngMetadataReader::create(strict: true)->read('photo.png');
```

## The hidden "flags" in the type

The 4 bytes of the `Type` are not arbitrary. **Bit 5** of each letter (its upper/lowercase) encodes four properties:

| Position | Uppercase | Lowercase |
|---|---|---|
| 1st letter | **Critical**: must be understood | **Ancillary**: can be ignored |
| 2nd letter | **Public**: defined in the spec | **Private**: for private use |
| 3rd letter | Correct (reserved, must be uppercase) | Invalidates the chunk in conforming streams |
| 4th letter | **Unsafe to copy** | **Safe to copy** |

Examples:

- `IHDR` — **C**ritical, **P**ublic, correct reserved, **U**nsafe. The mandatory header.
- `tEXt` — **a**ncillary, **p**ublic, correct, **s**afe-to-copy (lowercase). Text safe to copy.
- `vpAg` — ancillary, **p**rivate (Virtual Pagination), correct, safe. A private chunk of specific software.

### Why does this matter?

Because it lets the library **classify any chunk, even unknown ones**. An unknown chunk is still readable in terms of its properties: is it critical? is it private? is it safe to copy?

In the code, this is exposed by the `ChunkType` class:

```php
$chunk->type->isAncillary();  // true for "tEXt", false for "IHDR"
$chunk->type->isPrivate();    // true for "vpAg"
$chunk->type->isSafeToCopy(); // true for "tEXt"
```

## Critical vs. ancillary chunks

PNG defines **4 critical chunks** that every decoder must understand:

| Chunk | Role |
|---|---|
| `IHDR` | Header: dimensions, color type, compression |
| `PLTE` | Color palette (indexed images) |
| `IDAT` | The compressed image data |
| `IEND` | Marks the end of the file |

Everything else is **ancillary**: a reader can ignore them and still display the image. **Almost all metadata lives in ancillary chunks** — precisely the ones PNGMetadata cares about.

## How the library walks them

The reading flow (the `PngReader` class) is:

1. Verifies the 8-byte signature.
2. Reads the header (8 bytes) of the first chunk.
3. If it is `IEND`, stops.
4. Reads `Length`, `Type`, skips `Data` and `CRC`.
5. Yields a `RawChunk` object and goes back to step 2.

::: tip Performance: lazy loading
`RawChunk::data()` does not read the payload until asked. That is why walking a huge image (with a 50 MB `IDAT`) costs memory proportional to the chunk **headers**, not the image size. `IDAT` is never loaded into memory.
:::

## Next

Now you understand the format. Let's see how the code that reads it is organized: [Project architecture](/en/architecture/README.html).
