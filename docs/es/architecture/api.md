---
title: API pública
lang: es-ES
---

# API pública: el modelo tipado

PNGMetadata v2 expone **una sola** interfaz: `PngMetadataReader` produce un `MetadataDocument` inmutable y tipado.

```php
use PNGMetadata\PngMetadataReader;

$documento = PngMetadataReader::create()->read('foto.png');

$documento->header?->width;              // int
$documento->header?->colorType;          // enum ColorType
$documento->header?->interlaceMethod;    // enum InterlaceMethod

$documento->renderingIntent?->label();   // 'Perceptual', ...
$documento->texts;                       // TextEntry[] (tEXt + iTXt)
$documento->xmp;                         // array estructurado
$documento->exif;                        // secciones EXIF decodificadas
$documento->background;                  // ?BackgroundColor
$documento->thumbnailBytes();            // ?string (sin necesitar GD)
$documento->toArray();                   // array plano (estructura tipada)
```

## Entradas flexibles

El lector acepta **ruta, cadena binaria o stream**:

```php
PngMetadataReader::create()->read('/ruta/foto.png');
PngMetadataReader::create()->read($contenidoBinario);
PngMetadataReader::create()->read($recursoStream);
```

Esto es clave para integrarlo con frameworks que entregan streams (por ejemplo, el disco de Laravel).

## Modo estricto

```php
$documento = PngMetadataReader::create(strict: true)->read('foto.png');
```

Valida el CRC-32 de cada chunk y las reglas de orden (IHDR primero, IEND último). Útil para conformance, pero más lento.

## Excepciones

El contrato de error es la jerarquía tipada bajo `PNGMetadata\Exception\PngMetadataException`:

- `InvalidPngException` — el input no es un PNG o no es legible.
- `TruncatedDataException` — el archivo está incompleto.
- `ChunkDecodingException` — un chunk no se pudo decodificar.
- `CrcMismatchException` — CRC incorrecto (solo modo estricto).

## ¿Dónde quedó la fachada v1?

La antigua fachada de array de la v1 — iteración como objeto, rutas de string separadas por dos puntos, extracción que devuelve null al fallar, miniaturas renderizadas con GD, salida de string en dos columnas y códigos numéricos de error — se **eliminó en la 2.0.0**: v2 es una release con rotura. Cada punto de entrada de v1 tiene su equivalente tipado o una receta corta en la [guía de migración del README](https://github.com/joserick/PNGMetadata#upgrading-from-v1), y la rama `1.x` conserva la API antigua para consumidores legacy.

## Siguiente

Vuelve a la [arquitectura](/es/architecture/architecture.html) o lee sobre [PNG y sus chunks](/es/introduction/what-is-png.html).
