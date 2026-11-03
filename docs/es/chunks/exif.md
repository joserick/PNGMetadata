---
title: eXIf — datos EXIF
lang: es-ES
---

# eXIf — datos EXIF de la cámara

El chunk `eXIf` guarda los metadatos **EXIF**: la información que la cámara registró al tomar la foto. Apertura, velocidad de obturación, ISO, modelo, fecha, y hasta la miniatura.

::: tip Un poco de historia
El chunk `eXIf` es relativamente nuevo (se añadió en 2017). Antes, el EXIF viajaba escondido en chunks `tEXt` con keywords no estandarizados. Por eso la v1 de la librería tenía `extractTExif` *y* `extractExif`. La v2 unifica todo en `ExifDecoder`.
:::

## Qué es EXIF

**EXIF** (*Exchangeable Image File Format*) es un estándar (CIPA DC-008) que define cómo guardar metadatos técnicos dentro de archivos de imagen. En JPEG, el EXIF va en un segmento `APP1`. En PNG, va dentro del chunk `eXIf`.

## Estructura binaria

El contenido del chunk `eXIf` es un **perfil EXIF sin el header de JPEG**:

```
En JPEG:  [APP1 marker] [length] [ "Exif\0\0" ] [ TIFF data ]
En PNG:   (se omite todo lo anterior)          [ TIFF data ]
```

El TIFF data empieza con un **header de byte order**:

| Bytes | Significado |
|---|---|
| `49 49 2A 00` | `II*\0` — *little-endian* (Intel) |
| `4D 4D 00 2A` | `MM\0*` — *big-endian* (Motorola) |

### La validación del "número mágico"

::: tip Decisión de diseño
Antes de decodificar, la librería verifica estos 4 bytes. Si no coinciden, **omite el EXIF silenciosamente** (no lanza error) y sigue con el resto de metadatos. Esto cumple la recomendación de la especificación (§11.3.4.5.2) y evita un `TypeError` que existía en la v1 cuando `exif_read_data()` devolvía `false`.
:::

## Cómo se extrae

`ExifDecoder`:

1. Lee el payload del chunk.
2. Verifica los bytes mágicos (`II*\0` o `MM\0*`).
3. Escribe el payload en un stream en memoria (`php://temp`).
4. Llama a `exif_read_data($stream, null, true)` — PHP lo parsea por ti.
5. Si devuelve `false` o un array vacío, se omite el EXIF.

```php
// Uso
$documento->exif; // array de secciones EXIF decodificadas
```

### Qué devuelve `exif_read_data`

La extensión `exif` de PHP devuelve las secciones EXIF agrupadas:

```php
[
    'FILE'    => ['FileName' => ..., 'MimeType' => 'image/png', ...],
    'COMPUTED' => ['Width' => ..., 'Height' => ..., ...],
    'IFD0'    => ['Make' => 'SONY', 'Model' => ..., ...],
    'EXIF'    => ['DateTimeOriginal' => ..., 'ISOSpeedRatings' => ..., ...],
]
```

## Reglas especiales de eXIf

- **Solo un `eXIf` por archivo.** Si hay varios, el primero gana.
- El EXIF puede contener una **miniatura** embebida (más común en JPEG que en PNG). La librería la expone como bytes (`thumbnailBytes()`) sin necesidad de GD.
- Según la spec, el EXIF se considera **información histórica**: si la imagen fue editada, puede que ya no sea exacto. Los conflictos entre EXIF y otros chunks se dejan a decisión del decodificador.

## Precedencia de metadatos

Una misma fecha puede aparecer en tres sitios: EXIF (`eXIf`), XMP (`exif:` namespace) y texto (`tEXt`). La política documentada de PNGMetadata:

```
eXIf decodificado  >  keywords legacy de tEXt
```

Ambas fuentes permanecen accesibles por separado; solo la vista combinada prioriza el EXIF.

## Siguiente

El EXIF es metadata "plano" (clave → valor). El XMP es metadata **estructurada** en XML: [XMP](/es/chunks/xmp.html).
