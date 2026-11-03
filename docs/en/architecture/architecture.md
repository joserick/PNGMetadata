---
title: Layered architecture
lang: en-US
---

# Layered architecture

PNGMetadata is organized in **two layers plus a typed entry point**, with a strict rule: **dependencies point inward** (from outside in). Each layer only knows the layers below it.

```
┌────────────────────────────────────────────────────────────┐
│ LAYER 2 · Domain (typed model, NO I/O)                     │
│   Metadata/   MetadataDocument, HeaderData, TextEntry,     │
│               BackgroundColor, enums {ColorType, ...}      │
├────────────────────────────────────────────────────────────┤
│ LAYER 1 · Parsing (the ONLY one that touches bytes)        │
│   Parsing/    PngReader, RawChunk, ChunkType, BinaryStream │
│   Chunk/      one decoder per type + registry + builder    │
│   Exception/  typed domain exceptions                      │
└────────────────────────────────────────────────────────────┘
```

## Layer 1: Parsing — reading the bytes

The only layer doing input/output. Its job is to transform an input (path, binary string, or stream) into **raw chunks** (`RawChunk`), without interpreting their meaning.

Key components:

| Class | Responsibility |
|---|---|
| `BinaryStream` | Normalizes input (path / string / stream) to a binary stream. |
| `PngReader` | Verifies signature, walks chunks, validates CRC in strict mode. |
| `RawChunk` | A raw chunk: type, length, offset, CRC and **lazy** access to data. |
| `ChunkType` | The 4 type letters + their flags (ancillary/private/safe-to-copy). |

## Layer 2: Domain — giving meaning

Receives raw chunks and converts them into **typed objects** with meaning. This layer **does not touch files or streams**: it is pure logic.

```
RawChunk ("tEXt", bytes)  ──►  TextEntry { keyword: "Author", text: "José" }
RawChunk ("IHDR", bytes)  ──►  HeaderData { width: 800, colorType: RGBWithAlpha }
```

The end result is an immutable **`MetadataDocument`** (cannot be modified after creation) with typed accessors.

Values that can only take a fixed set of options are modeled with PHP **enums**:

```php
enum ColorType: int {
    case Grayscale = 0;
    case RGB = 2;
    case Palette = 3;
    case GrayscaleWithAlpha = 4;
    case RGBWithAlpha = 6;
}
```

## The decoders: the key pattern

Within Layer 1 there is a pattern worth highlighting. Each chunk type has its own **decoder**, and a **registry** relates them:

```php
final class ChunkDecoderRegistry
{
    public static function defaults(): array
    {
        return [
            new IhdrDecoder(),   // IHDR
            new TextDecoder(),   // tEXt
            new ItxtDecoder(),   // iTXt
            new ExifDecoder(),   // eXIf
            new SrgbDecoder(),   // sRGB
            new BkgdDecoder(),   // bKGD
        ];
    }
}
```

### Adding a new chunk = adding one class + one line

Thanks to this design, supporting a new chunk (e.g. `tIME`) does not require touching the reader or the core:

1. Create a `TimeDecoder` class implementing `ChunkDecoderInterface`.
2. Register it in `ChunkDecoderRegistry::defaults()`.

That is it. It is the **Open/Closed** principle: the core is closed to modifications but open to extensions.

## The full extraction flow

```
input (path | string | stream)
   │
   ▼
BinaryStream ──────► normalize to a binary stream
   │
   ▼
PngReader ─────────► verify signature + walk chunks ──► RawChunk (lazy data)
   │
   ▼
ChunkDecoderRegistry ──► the type's decoder decodes it (with IHDR context)
   │
   ▼
MetadataBuilder ───► mutable accumulator during the walk
   │
   ▼
MetadataDocument ──► immutable typed model   ◄── the only public API (PngMetadataReader::read)
```

### The IHDR context

Some chunks **cannot be interpreted without knowing the IHDR color type**. For example, `bKGD` (background color) has 1 byte for paletted images, 2 bytes for grayscale, or 6 bytes for RGB.

That is why `IhdrDecoder` stores the header in a `DecodingContext`, and later decoders consult it:

```php
$header = $context->requireHeader(); // throws if IHDR has not been read yet
```

### Failure isolation

A cross-cutting principle: **corrupt metadata must never prevent reading the rest**. If EXIF or XMP is damaged, it is silently omitted and the rest of the metadata is extracted normally.

## Next

With the structure in view, let's see how to use it from outside: [Public API](/en/architecture/api.html).
