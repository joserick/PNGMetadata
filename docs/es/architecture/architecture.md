---
title: Arquitectura en capas
lang: es-ES
---

# Arquitectura en capas

PNGMetadata se organiza en **dos capas más un punto de entrada tipado**, con una regla estricta: **las dependencias apuntan hacia adentro** (de fuera a dentro). Cada capa solo conoce a las que están por debajo de ella.

```
┌────────────────────────────────────────────────────────────┐
│ CAPA 2 · Dominio (modelo tipado, SIN I/O)                  │
│   Metadata/   MetadataDocument, HeaderData, TextEntry,     │
│               BackgroundColor, enums {ColorType, ...}      │
├────────────────────────────────────────────────────────────┤
│ CAPA 1 · Parsing (la ÚNICA que toca bytes)                 │
│   Parsing/    PngReader, RawChunk, ChunkType, BinaryStream │
│   Chunk/      un decoder por tipo + registry + builder     │
│   Exception/  excepciones tipadas de dominio               │
└────────────────────────────────────────────────────────────┘
```

## Capa 1: Parsing — leer los bytes

La única capa que hace operaciones de entrada/salida. Su trabajo es transformar una entrada (ruta, cadena binaria o stream) en **chunks crudos** (`RawChunk`), sin interpretar su significado.

Componentes clave:

| Clase | Responsabilidad |
|---|---|
| `BinaryStream` | Normaliza la entrada (ruta / string / stream) a un stream binario. |
| `PngReader` | Verifica firma, recorre chunks, valida CRC en modo estricto. |
| `RawChunk` | Un chunk crudo: tipo, longitud, offset, CRC y acceso **perezoso** a los datos. |
| `ChunkType` | Las 4 letras del tipo + sus banderas (ancillary/private/safe-to-copy). |

## Capa 2: Dominio — dar significado

Recibe los chunks crudos y los convierte en **objetos tipados** con significado. Esta capa **no toca archivos ni streams**: es pura lógica.

```
RawChunk ("tEXt", bytes)  ──►  TextEntry { keyword: "Author", text: "José" }
RawChunk ("IHDR", bytes)  ──►  HeaderData { width: 800, colorType: RGBWithAlpha }
```

El resultado final es un **`MetadataDocument`** inmutable (no se puede modificar tras crearse) con accesores tipados.

Los valores que solo pueden tomar un conjunto fijo de opciones se modelan con **enums** de PHP:

```php
enum ColorType: int {
    case Grayscale = 0;
    case RGB = 2;
    case Palette = 3;
    case GrayscaleWithAlpha = 4;
    case RGBWithAlpha = 6;
}
```

## Los decoders: el patrón clave

Dentro de la Capa 1 existe un patrón que merece mención aparte. Cada tipo de chunk tiene su propio **decoder**, y un **registry** los relaciona:

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

### Añadir un chunk nuevo = añadir una clase + una línea

Gracias a este diseño, soportar un chunk nuevo (por ejemplo `tIME`) no requiere tocar el lector ni el núcleo:

1. Crea una clase `TimeDecoder` que implemente `ChunkDecoderInterface`.
2. Regístrala en `ChunkDecoderRegistry::defaults()`.

Eso es todo. Es el principio **Open/Closed**: el núcleo está cerrado a modificaciones pero abierto a extensiones.

## El flujo completo de extracción

```
entrada (ruta | string | stream)
   │
   ▼
BinaryStream ──────► normaliza a un stream binario
   │
   ▼
PngReader ─────────► verifica firma + recorre chunks ──► RawChunk (datos perezosos)
   │
   ▼
ChunkDecoderRegistry ──► el decoder del tipo decodifica (con contexto IHDR)
   │
   ▼
MetadataBuilder ───► acumulador mutable durante el recorrido
   │
   ▼
MetadataDocument ──► modelo inmutable tipado  ◄── la única API pública (PngMetadataReader::read)
```

### El contexto IHDR

Hay chunks que **no se pueden interpretar sin saber el tipo de color** del IHDR. Por ejemplo, `bKGD` (color de fondo) tiene 1 byte si la imagen usa paleta, 2 bytes si es escala de grises, o 6 bytes si es RGB.

Por eso el `IhdrDecoder` guarda el header en un `DecodingContext`, y los decoders posteriores lo consultan:

```php
$header = $context->requireHeader(); // lanza si IHDR aún no se ha leído
```

### Aislamiento de fallos

Un principio transversal: **un metadato corrupto nunca debe impedir leer el resto**. Si el EXIF o el XMP están dañados, se omiten silenciosamente y el resto de metadatos se extrae con normalidad.

## Siguiente

Vista la estructura, veamos cómo usarla desde fuera: [API pública](/es/architecture/api.html).
