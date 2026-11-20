---
title: Text chunks — tEXt, zTXt, iTXt
lang: en-US
---

# Text chunks: tEXt, zTXt and iTXt

Text chunks store **human-readable** metadata: title, author, copyright, the software that created the image, etc. There are three variants depending on encoding and compression.

## The text family

| Chunk | Encoding | Compression | Extras |
|---|---|---|---|
| `tEXt` | Latin-1 (ISO-8859-1) | No | keyword + text |
| `zTXt` | Latin-1 | Yes (zlib) | same as tEXt but compressed |
| `iTXt` | UTF-8 | Optional | language tag + translated keyword |

All three share the **keyword** + **text** pattern. The keyword is a standard label:

| Registered keyword | Meaning |
|---|---|
| `Title` | Short title |
| `Author` | Author |
| `Description` | Description |
| `Copyright` | Copyright |
| `Creation Time` | Creation date |
| `Software` | Program that generated the image |
| `Disclaimer` | Legal disclaimer |
| `Warning` | Warning |
| `Source` | Source |
| `Comment` | Miscellaneous comment |

## tEXt — the simplest

```
keyword (1-79 bytes, Latin-1) | \0 | text (Latin-1)
```

Keyword and text are separated by a null byte (`\0`). Everything in **Latin-1** (ISO-8859-1) encoding, which only covers Western European characters.

### Charset conversion

::: danger Important detail
tEXt uses **Latin-1**, but PHP works in **UTF-8** by default. The library automatically converts from Latin-1 to UTF-8 when extracting:

```php
// The byte E9 in Latin-1 is "é"
mb_convert_encoding("Caf\xE9", 'UTF-8', 'ISO-8859-1'); // "Café"
```

Without this conversion, "Café" would come out corrupted (`CafÃ©`). `TextDecoder::latin1ToUtf8()` handles it.
:::

### Structure and extraction

```
Bytes:  41 75 74 68 6F 72 00 4A 6F 73 E9
        └──── "Author" ────┘ └ "José" (E9=é in Latin-1) ┘
```

`TextDecoder` finds the first `\0`, splits keyword from text, and converts both to UTF-8. The result is a `TextEntry` object.

## zTXt — compressed text

```
keyword (Latin-1) | \0 | compression method (1 byte, always 0) | compressed text (zlib)
```

Identical to `tEXt` in content, but the text is compressed with **zlib/deflate** to save space. Extraction inflates the text with `gzuncompress()`.

::: info Status
`zTXt` is supported since `2.0.0-beta.2`. It is common in files written by ImageMagick and GIMP.
:::

`ZtxtDecoder` finds the first `\0`, validates the 1-79 byte keyword, requires compression method `0`, inflates the remaining bytes, and then converts keyword and text from Latin-1 to UTF-8. The resulting `TextEntry` keeps `chunkType = 'zTXt'` and `compressed = true`; multiple chunks are preserved in file order.

## iTXt — international text (and the home of XMP)

The most complete. Designed for **Unicode** and multiple languages:

```
keyword (Latin-1) \0
compression flag (1 byte: 0 = uncompressed, 1 = compressed)
compression method (1 byte: always 0 = zlib)
language tag (BCP47, e.g. "es", "en-GB") \0
translated keyword (UTF-8) \0
text (UTF-8, may be compressed)
```

Extra fields vs tEXt:

- **Compression flag** — allows compressing only the text.
- **Language tag** — language per the [BCP47](https://en.wikipedia.org/wiki/IETF_language_tag) standard (`es`, `en-GB`, `zh-Hans`...).
- **Translated keyword** — the keyword translated into the indicated language.

### The crucial detail: the text is NOT null-terminated

Unlike the other fields, the **text** field of iTXt has **no null terminator**. Its length is computed by subtracting from the total chunk size. That is why extraction must be **positional** (reading field by field with an offset), not an `explode()`.

::: warning Historical bug
v1 extracted iTXt by doing `ltrim("\0")` on the whole payload. That "worked" by accident only for uncompressed XMP (where there are 5 consecutive nulls that `ltrim` eats). With compressed XMP, the XML came out corrupted. v2 fixed it with positional parsing.
:::

### How it is extracted (ItxtDecoder)

```
keyword \0 ──► read up to the first \0
flag, method ──► read 2 bytes
language tag \0 ──► read up to \0
translated keyword \0 ──► read up to \0
text ──► the rest (if flag=1, inflate with zlib)
```

```php
$document->texts; // TextEntry[] with keyword, text, languageTag, translatedKeyword, compressed
```

## Visual summary

```
tEXt:  keyword\0text
zTXt:  keyword\0method_compressedText
iTXt:  keyword\0flag\0method\0lang\0translated\0text   ← positional
```

## Next

`iTXt` is also the vehicle for XMP (structured XML metadata). But first, let's see EXIF: [eXIf](/en/chunks/exif.html).
