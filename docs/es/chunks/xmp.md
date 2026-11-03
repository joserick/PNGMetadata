---
title: XMP — metadatos estructurados
lang: es-ES
---

# XMP — metadatos estructurados en XML

**XMP** (*Extensible Metadata Platform*) es un estándar de Adobe para guardar metadatos **estructurados** en formato XML. A diferencia del EXIF (lista plana de claves), XMP organiza la información en **namespaces** (espacios de nombres) y puede representar estructuras complejas.

## Dónde vive el XMP

En PNG, el XMP se guarda dentro de un chunk `iTXt` con una keyword especial:

```
keyword = "XML:com.adobe.xmp"
```

Por eso el flujo de extracción es: `iTXt` → detectar la keyword XMP → parsear el XML.

## Un ejemplo de paquete XMP

Un paquete XMP se ve así (recortado):

```xml
<x:xmpmeta xmlns:x="adobe:ns:meta/">
  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Mi Título</dc:title>
      <dc:creator>José</dc:creator>
    </rdf:Description>
  </rdf:RDF>
</x:xmpmeta>
```

Fíjate en los prefijos: `dc`, `rdf`, `x`. Cada uno es un **namespace** con su URI.

## Los namespaces más comunes

| Prefijo | Qué contiene |
|---|---|
| `dc` (Dublin Core) | title, creator, description, rights... |
| `xmp` | creatorTool, rating... |
| `xmpMM` | gestión de documentos, historial |
| `xmpRights` | copyright, uso |
| `photoshop` | datos de edición de Photoshop |
| `crs` (Camera Raw) | ajustes de revelado |
| `tiff` / `exif` | **duplican** información EXIF en formato XML |
| `aux` | lentes, información adicional |

::: tip Redundancia
La misma fecha puede estar en EXIF (`eXIf`), en XMP (`exif:DateTimeOriginal`) y en `tEXt`. Son tres mecanismos que evolucionaron por separado y pueden contradecirse. La política de precedencia la verás más abajo.
:::

## Cómo se extrae en el código

`XmpDecoder`:

1. Recibe el texto XML (ya extraído por `ItxtDecoder`).
2. Lo carga con `DOMDocument` (la extensión XML de PHP).
3. Recorre los elementos con `getElementsByTagNameNS('*', '*')`.
4. Agrupa los valores por prefijo de namespace.

```php
// Uso
$documento->xmp; // ['dc' => ['title' => 'Mi Título', ...], ...]
```

### Aislamiento de fallos

Si el XML está corrupto o malformado, `XmpDecoder::parse()` devuelve `null` y el XMP se **omite silenciosamente**. El resto de metadatos se extrae sin problemas. Un paquete XMP roto nunca debe tumbar la extracción.

## RDF y la complejidad oculta

XMP se basa en **RDF** (*Resource Description Framework*), un modelo de grafos con conceptos como `rdf:Bag`, `rdf:Seq` y `rdf:Alt` (listas ordenadas, no ordenadas, alternativas). Esto introduce complejidad: un campo puede tener múltiples valores.

::: details Diferencia con la v1
La v1 "aplanaba" el XMP con una lógica recursiva idiosincrásica (listas de prefijos a eliminar, concatenación de valores con comas). La v2 usa un enfoque más limpio basado en XPath. Por eso la estructura del array XMP puede diferir ligeramente entre la API v1 (formateador legacy) y la v2 tipada — documentado en el CHANGELOG.
:::

## Siguiente

El XMP y el EXIF describen *quién* y *cómo* se tomó la imagen. Los chunks de **color** describen *cómo debe verse*: [Color](/es/chunks/color.html).
