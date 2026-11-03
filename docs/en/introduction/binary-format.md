---
title: The binary format
lang: en-US
---

# The binary format: bytes, bits and endianness

To understand how metadata is extracted, you need to master three concepts: the **byte**, the **byte order** (*endianness*), and how numbers are **packed** inside a file.

## Byte and bit

The minimum unit of a file is the **byte**: 8 bits, capable of representing 256 values (from 0 to 255).

```
One byte = 8 bits = 0000 0000 to 1111 1111 = 0 to 255
```

When you need to store a number larger than 255, you use **several consecutive bytes**.

## How numbers are stored: "big-endian"

PNG stores all multi-byte numbers in **big-endian** order (also called *network byte order*): **the most significant byte comes first**.

Imagine the number `800` in binary. In 2 bytes it is written `0000 0011 0010 0000`:

```
Big-endian (what PNG uses):     [0000 0011] [0010 0000]  →  03 20
Little-endian (NOT used by PNG):[0010 0000] [0000 0011]  →  20 03
```

`0x0320 = 800`. As you can see, the same number occupies the same bytes but **in reverse order** depending on the convention.

### Why does it matter?

Because when reading a PNG you have to rebuild the numbers. If you read the bytes `03 20` and interpret them in the wrong order, you get `0x2003 = 8195` instead of `800`. A classic, silent error.

## The number types PNG uses

| Type | Bytes | Range | In PHP (`unpack`) |
|---|---|---|---|
| Byte (uint8) | 1 | 0–255 | `C` |
| Unsigned 16-bit integer | 2 | 0–65535 | `n` (big-endian) |
| Unsigned 32-bit integer | 4 | 0–4,294,967,295 | `N` (big-endian) |

Real example from the IHDR chunk: it stores the image width as a 4-byte integer. For a `800` pixel image:

```
Bytes in the file:  00 00 03 20
            unpack:  N  →  800
```

## The signature: 8 magic bytes

Every PNG starts with **8 fixed bytes** that identify it unambiguously:

```
89 50 4E 47 0D 0A 1A 0A
```

Partially legible in ASCII it reads `\x89PNG\r\n\x1a\n`. Those bytes are deliberately "weird" to:

- Detect that the file is a PNG (not a JPEG, GIF, etc.).
- Detect corruption if the file was transferred in text mode (bytes `0D 0A` and `1A` would be corrupted).

PNGMetadata verifies this signature before doing anything else. If it does not match, it throws a domain exception (`InvalidPngException`).

## Packing and unpacking

The technical verb is **pack/unpack**. In PHP, `unpack()` converts raw bytes into variables:

```php
// 4 big-endian bytes → unsigned 32-bit integer
$width = unpack('N', "\x00\x00\x03\x20")[1]; // 800

// 2 big-endian bytes → 16-bit integer
$value = unpack('n', "\x03\x20")[1]; // 800
```

::: tip Technical note
`unpack()` returns an array **indexed from 1**, not 0. That is why you access it with `[1]`. It is a historical PHP quirk.
:::

## Why all this matters for metadata

Each metadata chunk is, at its core, a sequence of bytes with an agreed meaning. For example:

- The `sRGB` chunk is **1 byte** encoding the "rendering intent" (0=perceptual, 1=relative colorimetric...).
- The `tIME` chunk is **7 bytes**: year (2), month, day, hour, minute, second (1 each).
- The `cLLI` chunk is **8 bytes**: two 4-byte integers measuring luminance.

Knowing how to read these bytes is exactly what the library's parsing layer does.

## Next

With bytes under your belt, let's see how each block is structured: [Anatomy of a chunk](/en/introduction/chunk-anatomy.html).
