---
title: Chunks de texto — tEXt, zTXt, iTXt
lang: es-ES
---

# Chunks de texto: tEXt, zTXt e iTXt

Los chunks de texto guardan metadatos **legibles por humanos**: título, autor, copyright, software que creó la imagen, etc. Hay tres variantes según la codificación y la compresión.

## La familia de texto

| Chunk | Codificación | Compresión | Extras |
|---|---|---|---|
| `tEXt` | Latin-1 (ISO-8859-1) | No | keyword + texto |
| `zTXt` | Latin-1 | Sí (zlib) | igual que tEXt pero comprimido |
| `iTXt` | UTF-8 | Opcional | language tag + keyword traducida |

Los tres comparten el patrón **keyword** (palabra clave) + **texto**. La keyword es una etiqueta estándar:

| Keyword registrada | Significado |
|---|---|
| `Title` | Título corto |
| `Author` | Autor |
| `Description` | Descripción |
| `Copyright` | Copyright |
| `Creation Time` | Fecha de creación |
| `Software` | Programa que generó la imagen |
| `Disclaimer` | Aviso legal |
| `Warning` | Advertencia |
| `Source` | Fuente |
| `Comment` | Comentario vario |

## tEXt — el más simple

```
keyword (1-79 bytes, Latin-1) | \0 | texto (Latin-1)
```

La keyword y el texto se separan con un byte nulo (`\0`). Todo en codificación **Latin-1** (ISO-8859-1), que solo cubre caracteres de Europa occidental.

### La conversión de charset

::: danger Detalle importante
tEXt usa **Latin-1**, pero PHP trabaja en **UTF-8** por defecto. La librería convierte automáticamente de Latin-1 a UTF-8 al extraer:

```php
// El byte E9 en Latin-1 es "é"
mb_convert_encoding("Caf\xE9", 'UTF-8', 'ISO-8859-1'); // "Café"
```

Sin esta conversión, un "Café" saldría corrupto (`CafÃ©`). `TextDecoder::latin1ToUtf8()` se encarga de ello.
:::

### Estructura y extracción

```
Bytes:  41 75 74 68 6F 72 00 4A 6F 73 E9
        └──── "Author" ────┘ └ "José" (E9=é en Latin-1) ┘
```

`TextDecoder` busca el primer `\0`, separa keyword de texto, y convierte ambos a UTF-8. El resultado es un objeto `TextEntry`.

## zTXt — texto comprimido

```
keyword (Latin-1) | \0 | método de compresión (1 byte, siempre 0) | texto comprimido (zlib)
```

Es idéntico a `tEXt` en contenido, pero el texto se comprime con **zlib/deflate** para ahorrar espacio. La extracción infla el texto con `gzuncompress()`.

::: info Estado
`zTXt` es compatible desde `2.0.0-beta.2`. Es común en archivos escritos por ImageMagick y GIMP.
:::

`ZtxtDecoder` busca el primer `\0`, valida la keyword de 1-79 bytes, exige el método de compresión `0`, infla los bytes restantes y después convierte keyword y texto de Latin-1 a UTF-8. El `TextEntry` resultante conserva `chunkType = 'zTXt'` y `compressed = true`; los chunks repetidos se preservan en orden de archivo.

## iTXt — texto internacional (y el hogar del XMP)

El más completo. Pensado para **Unicode** y múltiples idiomas:

```
keyword (Latin-1) \0
compression flag (1 byte: 0 = sin comprimir, 1 = comprimido)
compression method (1 byte: siempre 0 = zlib)
language tag (BCP47, ej. "es", "en-GB") \0
translated keyword (UTF-8) \0
text (UTF-8, puede ir comprimido)
```

Campos extra frente a tEXt:

- **Compression flag** — permite comprimir solo el texto.
- **Language tag** — idioma según el estándar [BCP47](https://es.wikipedia.org/wiki/BCP_47) (`es`, `en-GB`, `zh-Hans`...).
- **Translated keyword** — la keyword traducida al idioma indicado.

### El detalle crucial: el texto NO termina en nulo

A diferencia de los otros campos, el campo **text** de iTXt **no lleva terminador nulo**. Su longitud se calcula restando del tamaño total del chunk. Por eso la extracción debe ser **posicional** (leer campo a campo con un offset), no un `explode()`.

::: warning Bug histórico
La v1 extraía iTXt haciendo `ltrim("\0")` del payload completo. Eso "funcionaba" por casualidad solo para XMP sin comprimir (donde hay 5 nulos seguidos que el `ltrim` se comía). Con XMP comprimido, el XML salía corrupto. La v2 lo arregló con parseo posicional.
:::

### Cómo se extrae (ItxtDecoder)

```
keyword \0 ──► leer hasta el primer \0
flag, method ──► leer 2 bytes
language tag \0 ──► leer hasta \0
translated keyword \0 ──► leer hasta \0
text ──► el resto (si flag=1, inflar con zlib)
```

```php
$documento->texts; // TextEntry[] con keyword, text, languageTag, translatedKeyword, compressed
```

## Resumen visual

```
tEXt:  keyword\0text
zTXt:  keyword\0method_textoComprimido
iTXt:  keyword\0flag\0method\0lang\0traducida\0texto   ← posicional
```

## Siguiente

`iTXt` es también el vehículo del XMP (metadata estructurado XML). Pero antes, veamos el EXIF: [eXIf](/es/chunks/exif.html).
