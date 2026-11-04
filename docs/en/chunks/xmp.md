---
title: XMP — structured metadata
lang: en-US
---

# XMP — structured XML metadata

**XMP** (*Extensible Metadata Platform*) is an Adobe standard for storing **structured** metadata in XML format. Unlike EXIF (a flat list of keys), XMP organizes information into **namespaces** and can represent complex structures.

## Where XMP lives

In PNG, XMP is stored inside an `iTXt` chunk with a special keyword:

```
keyword = "XML:com.adobe.xmp"
```

So the extraction flow is: `iTXt` → detect the XMP keyword → parse the XML.

## An example XMP packet

An XMP packet looks like this (trimmed):

```xml
<x:xmpmeta xmlns:x="adobe:ns:meta/">
  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>My Title</dc:title>
      <dc:creator>José</dc:creator>
    </rdf:Description>
  </rdf:RDF>
</x:xmpmeta>
```

Notice the prefixes: `dc`, `rdf`, `x`. Each is a **namespace** with its URI.

## The most common namespaces

| Prefix | Contents |
|---|---|
| `dc` (Dublin Core) | title, creator, description, rights... |
| `xmp` | creatorTool, rating... |
| `xmpMM` | document management, history |
| `xmpRights` | copyright, usage |
| `photoshop` | Photoshop editing data |
| `crs` (Camera Raw) | develop settings |
| `tiff` / `exif` | **duplicate** EXIF information in XML format |
| `aux` | lenses, additional information |

::: tip Redundancy
The same date can be in EXIF (`eXIf`), in XMP (`exif:DateTimeOriginal`) and in `tEXt`. These are three mechanisms that evolved separately and can contradict each other. See the precedence policy below.
:::

## How it is extracted in the code

`XmpDecoder`:

1. Receives the XML text (already extracted by `ItxtDecoder`).
2. Loads it with `DOMDocument` (PHP's XML extension).
3. Walks the elements with `getElementsByTagNameNS('*', '*')`.
4. Groups values by namespace prefix.

```php
// Usage
$document->xmp; // ['dc' => ['title' => 'My Title', ...], ...]
```

### Failure policy

If the XML is corrupt or malformed, `XmpDecoder::parse()` raises a decode failure: in lenient mode the XMP is omitted and the rest of the metadata is extracted normally; in strict mode `read()` reports the broken packet. A packet that is well-formed but carries no properties is simply empty - never an error in any mode. A broken XMP packet must never bring down lenient extraction.

## RDF and the hidden complexity

XMP is based on **RDF** (*Resource Description Framework*), a graph model with concepts like `rdf:Bag`, `rdf:Seq` and `rdf:Alt` (ordered, unordered, alternative lists). This introduces complexity: a field can have multiple values.

::: details Difference from v1
v1 "flattened" XMP with an idiosyncratic recursive logic (lists of prefixes to remove, comma concatenation of values). v2 uses a cleaner XPath-based approach and exposes XMP as a structured array. The legacy formatter that reproduced v1's flat shape was removed in 2.0.0.
:::

## Next

XMP and EXIF describe *who* and *how* the image was taken. The **color** chunks describe *how it should look*: [Color](/en/chunks/color.html).
