---
title: API pública
lang: es-ES
---

# API pública: dos formas de usar la librería

PNGMetadata v2 ofrece **dos interfaces** que conviven:

1. La **API v1** (fachada `PNGMetadata`) — un `ArrayObject` con un array anidado, pensada para compatibilidad con la versión original.
2. La **API v2** (`PngMetadataReader`) — un modelo tipado e inmutable, más potente.

Elige según lo que necesites: compatibilidad (v1) o tipado estricto (v2).

## API v1 — la fachada clásica

Es la forma más simple y la que usan los consumidores de la v1 sin cambios.

```php
use PNGMetadata\PNGMetadata;

$png = new PNGMetadata('foto.png');

// Acceso por ruta con dos puntos
$fecha = $png->get('exif:DateTime');   // '2019:09:08 23:01:23'
$ancho = $png->get('IHDR:ImageWidth'); // 800

// Todo el array
$todo = $png->toArray();

// Imprimir en dos columnas
echo $png;

// Miniatura embebida (necesita ext-gd)
$thumb = $png->getThumbnail(); // \GdImage|false
```

### Métodos estáticos

```php
PNGMetadata::extract('foto.png'); // ?PNGMetadata (null si falla, nunca lanza)
PNGMetadata::isPNG('foto.png');   // bool
PNGMetadata::getType('foto.png'); // int|false (IMAGETYPE_PNG, IMAGETYPE_GIF, ...)
```

### La forma del array v1

El array `toArray()` agrupa por tipo de metadato y se ordena con `ksort()`:

```php
[
    'IHDR' => [
        'ImageWidth'  => 800,
        'ImageHeight' => 600,
        'BitDepth'    => 8,
        'ColorType'   => 'RGB with Alpha',
        'Compression' => 'Deflate/Inflate',
        'Filter'      => 'Adaptive',
        // Nota: "Interlace" se omite intencionalmente (quirk de v1)
    ],
    'bKGD'  => '100 200 300',
    'exif'  => [ 'DateTime' => '...', 'Make' => 'SONY', ... ],
    'sRGB'  => 'Perceptual',
]
```

::: warning Peculiaridad de compatibilidad
La v1 leía solo 6 de los 7 campos del IHDR, así que **el array v2 reproduce esa omisión** de la clave `Interlace` para no romper consumidores. El dato sí está disponible en la API v2 tipada.
:::

## API v2 — el modelo tipado

Para código nuevo, es la opción recomendada: objetos con tipos, enums y sin strings ambiguos.

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
$documento->thumbnailBytes();            // ?string (sin necesitar GD)
```

### Entradas flexibles

A diferencia de la v1 (solo ruta en disco), la API v2 acepta **ruta, cadena binaria o stream**:

```php
PngMetadataReader::create()->read('/ruta/foto.png');
PngMetadataReader::create()->read($contenidoBinario);
PngMetadataReader::create()->read($recursoStream);
```

Esto es clave para integrarlo con frameworks que entregan streams (por ejemplo, el disco de Laravel).

### Modo estricto

```php
$documento = PngMetadataReader::create(strict: true)->read('foto.png');
```

Valida el CRC-32 de cada chunk y las reglas de orden (IHDR primero, IEND último). Útil para conformance, pero más lento.

## Tabla comparativa

| Característica | API v1 (`PNGMetadata`) | API v2 (`PngMetadataReader`) |
|---|---|---|
| Tipo de retorno | `ArrayObject` / array | `MetadataDocument` (inmutable, tipado) |
| Entradas | Solo ruta en disco | Ruta, string, stream |
| Validación CRC | No | Sí (modo strict) |
| Errores | `extract()` → null | Excepciones de dominio tipadas |
| Miniatura | `\GdImage` (requiere GD) | bytes (sin GD) |
| Recomendada para | Compatibilidad / código legacy | Código nuevo |

## Excepciones

Ambas APIs comparten una jerarquía de excepciones bajo `PNGMetadata\Exception\PngMetadataException`:

- `InvalidPngException` — el input no es un PNG o no es legible.
- `TruncatedDataException` — el archivo está incompleto.
- `ChunkDecodingException` — un chunk no se pudo decodificar.
- `CrcMismatchException` — CRC incorrecto (solo modo estricto).

La fachada v1 conserva los códigos históricos de `InvalidArgumentException` (101 = ruta vacía, 102 = no existe, 103 = no es PNG) para no romper `catch` existentes.
