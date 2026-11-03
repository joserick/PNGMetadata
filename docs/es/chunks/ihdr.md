---
title: IHDR — la cabecera
lang: es-ES
---

# IHDR — la cabecera de la imagen

El chunk `IHDR` (*Image Header*) es el **primero** de todo PNG y el único chunk crítico que PNGMetadata decodifica. Describe las dimensiones y el formato de los píxeles.

::: tip Por qué es especial
IHDR es "el contexto" del resto de chunks. Varios chunks auxiliares (como `bKGD`) solo se pueden interpretar sabiendo el **tipo de color** que IHDR declara. Por eso la librería lo decodifica primero y lo guarda en el contexto.
:::

## Estructura binaria

IHDR mide exactamente **13 bytes**, siempre:

| Campo | Bytes | Tipo | Significado |
|---|---|---|---|
| Width | 0–3 | uint32 | Ancho en píxeles |
| Height | 4–7 | uint32 | Alto en píxeles |
| Bit depth | 8 | uint8 | Bits por muestra de color |
| Color type | 9 | uint8 | Tipo de color (ver abajo) |
| Compression | 10 | uint8 | Método de compresión (siempre 0) |
| Filter | 11 | uint8 | Método de filtro (siempre 0) |
| Interlace | 12 | uint8 | Entrelazado (0 o 1) |

### Ejemplo: imagen de 800×600

```
Bytes:  00 00 03 20 | 00 00 02 58 | 08 | 06 | 00 | 00 | 00
        └─ width ──┘ └─ height ──┘  └─┘ └─┘ └─┘ └─┘ └─┘
          800           600         BD  CT  C   F   I
```

Interpretación: ancho 800, alto 600, profundidad de bits 8, tipo de color 6 (RGBA), sin compresión especial, sin filtro, no entrelazado.

## El tipo de color (color type)

El byte 9 codifica cómo se organizan los canales de color:

| Valor | Enum (`ColorType`) | Etiqueta | Canales |
|---|---|---|---|
| 0 | `Grayscale` | Grayscale | 1 (gris) |
| 2 | `RGB` | RGB | 3 (rojo, verde, azul) |
| 3 | `Palette` | Palette | 1 índice a paleta |
| 4 | `GrayscaleWithAlpha` | Grayscale with Alpha | 2 (gris + alfa) |
| 6 | `RGBWithAlpha` | RGB with Alpha | 4 (RGBA) |

::: warning Validación cruzada
No todas las combinaciones de tipo de color + profundidad de bits son válidas. Por ejemplo, una paleta (tipo 3) solo admite 1, 2, 4 u 8 bits por muestra, nunca 16. `IhdrDecoder` valida esta matriz y lanza `ChunkDecodingException` ante combinaciones imposibles.
:::

### Profundidades válidas por tipo

| Tipo de color | Profundidades permitidas |
|---|---|
| Grayscale (0) | 1, 2, 4, 8, 16 |
| RGB (2) | 8, 16 |
| Palette (3) | 1, 2, 4, 8 |
| Grayscale + Alpha (4) | 8, 16 |
| RGB + Alpha (6) | 8, 16 |

## Cómo se extrae en el código

La clase `IhdrDecoder`:

1. Lee los 13 bytes del chunk.
2. Los desempaqueta con `unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', ...)`.
3. Convierte el byte de tipo de color a un enum `ColorType`.
4. Valida la combinación tipo + profundidad.
5. Guarda el `HeaderData` resultante en el **contexto** (para los chunks que dependen de él) y en el builder.

```php
// Uso desde la API v2
$documento = PngMetadataReader::create()->read('foto.png');

$documento->header?->width;          // 800 (int)
$documento->header?->height;         // 600 (int)
$documento->header?->bitDepth;       // 8
$documento->header?->colorType;      // ColorType::RGBWithAlpha
$documento->header?->interlaceMethod; // InterlaceMethod::None
```

## El entrelazado (interlace)

El byte 12 admite dos valores:

| Valor | Enum | Significado |
|---|---|---|
| 0 | `None` | Los píxeles se guardan en orden normal |
| 1 | `Adam7` | Entrelazado Adam7 (7 pasadas progresivas) |

Para la extracción de metadatos, el entrelazado **no afecta** a los metadatos: es una propiedad de cómo se guardan los píxeles, que PNGMetadata ignora por diseño.

::: details Diferencia con la v1
La v1 leía solo 6 de los 7 campos de IHDR (se le olvidaba el entrelazado). La v2 siempre lee los 7: `interlaceMethod` forma parte del modelo tipado. El formateador legacy que reproducía esa omisión se eliminó en la 2.0.0.
:::

## Siguiente

IHDR describe la imagen. Los chunks de **texto** describen el contexto: [tEXt, zTXt e iTXt](/es/chunks/text.html).
