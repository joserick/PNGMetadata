---
title: Glossary
lang: en-US
---

# Glossary

Quick reference of terms used in this documentation and in the code.

## Binary fundamentals

- **Bit** — the smallest unit of information: 0 or 1.
- **Byte / octet** — 8 bits; a number between 0 and 255.
- **Big-endian** — byte order where the most significant comes first. The one PNG uses.
- **Little-endian** — the opposite order (used by Intel). EXIF can be either.
- **Pack / unpack** — packing values into bytes and unpacking bytes into values. In PHP, `pack()` and `unpack()`.
- **uint8 / uint16 / uint32** — unsigned integer of 8/16/32 bits.

## PNG format

- **Chunk** — data block with type, content and CRC. The fundamental unit of PNG.
- **Signature** — the initial 8 bytes identifying a file as PNG.
- **CRC** — *Cyclic Redundancy Check*; an integrity checksum.
- **Critical chunk** — a chunk that must be understood (IHDR, PLTE, IDAT, IEND).
- **Ancillary chunk** — an auxiliary chunk that can be ignored. Almost all metadata lives here.
- **Safe-to-copy** — a chunk property: whether an editor can copy it as-is when rewriting the file.
- **IHDR** — the header chunk with dimensions and color type.

## Color

- **Color type** — how color channels are organized (gray, RGB, palette, with/without alpha).
- **Bit depth** — bits per color sample (1–16).
- **Gamma** — exponent of the power transfer function; controls perceived brightness.
- **Primaries** — the reference red, green and blue colors defining a color space.
- **Chromaticities** — the x,y coordinates of the primaries and white point in CIE space.
- **White point** — the color considered "white" in a color space.
- **Color space** — the model defining how numbers translate into colors.
- **sRGB** — the standard web color space.
- **Rendering intent** — color conversion strategy: perceptual, relative colorimetric, saturation, absolute colorimetric.
- **ICC profile** — a file mathematically describing a color space.
- **Transfer function** — the function relating signal to luminance.
- **PQ** — Perceptual Quantizer, an absolute HDR transfer function.
- **HLG** — Hybrid Log-Gamma, a relative HDR transfer function.
- **SDR / HDR** — standard / high dynamic range.
- **Mastering display** — the reference display used when creating the content.
- **MaxCLL / MaxFALL** — maximum pixel / average-frame brightness.
- **Tone mapping** — adapting content to the display's capabilities.

## Metadata

- **EXIF** — a standard for technical camera metadata.
- **XMP** — Adobe's extensible metadata platform, based on XML/RDF.
- **RDF** — *Resource Description Framework*; the XMP data model.
- **Namespace** — an XML namespace grouping terms under a prefix.
- **BCP47** — the language tag standard (e.g. `es`, `en-GB`).
- **Latin-1 / ISO-8859-1** — Western European character encoding.
- **UTF-8** — universal Unicode encoding.
- **zlib / deflate** — the compression algorithm used in PNG.
- **Dublin Core** — a standard metadata set (`dc:` in XMP).

## Project techniques

- **Lazy payload** — not reading a chunk's content until asked, saving memory.
- **Decoding context** — the context (already-decoded IHDR) that decoders share.
- **Decoder** — a class that turns a raw chunk into meaningful data.
- **Registry** — a map relating each chunk type to its decoder.
- **Facade** — a layer exposing a simple API over a more complex system (the v1 facade was removed in 2.0.0).
- **Strict mode** — validation mode with CRC and ordering rules.

## Roadmap

- **Phase 1** — core + chunk decoders (completed). Chunks: IHDR, tEXt, iTXt/XMP, eXIf, sRGB, bKGD.
- **Phase 2** — classic coverage: zTXt, iCCP, gAMA, cHRM, tRNS, tIME, pHYs, sBIT, PLTE + color-precedence resolver.
- **Phase 3** — PNG 3.0: cICP, mDCV, cLLI, APNG, `image/apng`.
- **Phase 4** — Laravel package (separate repository) consuming this core.
