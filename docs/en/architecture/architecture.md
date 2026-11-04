---
title: Layered architecture
lang: en-US
---

# Layered architecture

PNGMetadata is organized in **two layers plus a typed entry point**, with a strict rule: **dependencies point inward** (from outside in). Each layer only knows the layers below it. Why this shape, and what was rejected, is recorded in [ADR-001](/en/decisions/ADR-001-two-layers-plus-typed-entry-point.html).

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

Payload laziness is a decision, not an optimization detail: reading the labels of a 50 MB image must not cost 50 MB of RAM — see [ADR-002](/en/decisions/ADR-002-lazy-chunk-payloads.html).

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

That is it. It is the **Open/Closed** principle: the core is closed to modifications but open to extensions. The alternatives that were weighed (a `match` in the reader, decoders returning values, header injected by constructor) are recorded in [ADR-003](/en/decisions/ADR-003-one-decoder-per-chunk-type.html).

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

The same context also carries the mode and whether image data has been seen — the state behind ADR-007 and ADR-008.

### Failure policy

A cross-cutting rule governed by the reader mode: a corrupt or uninterpretable chunk **never prevents reading the rest**. In lenient mode (the default) the offending chunk is omitted and extraction continues; in strict mode the same violation raises a typed domain exception naming the chunk and its byte offset — there the point is to fail the conformance check, not to lose data. IHDR is the exception: its failure is fatal in both modes because later chunks lose their interpretation context. Unknown ancillary chunks are never an error. The policy and the violations it forgave before unification are in [ADR-007](/en/decisions/ADR-007-mode-governed-decode-failure-policy.html); the duplicate and placement rules that depend on it are in [ADR-008](/en/decisions/ADR-008-first-wins-singletons-and-exif-placement.html).

## Next

With the structure in view, let's see how to use it from outside: [Public API](/en/architecture/api.html).
