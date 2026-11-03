---
title: Anatomía de un chunk
lang: es-ES
---

# Anatomía de un chunk

El chunk es la unidad fundamental del formato PNG. Comprender su estructura es comprender el 90% de cómo funciona la extracción de metadatos.

## Las cuatro partes de un chunk

Cada chunk tiene siempre la misma estructura:

```
┌──────────────┬──────────────┬────────────────────┬──────────────┐
│  Length      │  Type        │  Data              │  CRC         │
│  (4 bytes)   │  (4 bytes)   │  (Length bytes)    │  (4 bytes)   │
│  longitud    │  tipo/etiqueta│  contenido         │  verificación│
└──────────────┴──────────────┴────────────────────┴──────────────┘
```

1. **Length** — entero de 4 bytes (big-endian) que indica cuántos bytes tiene `Data`. Máximo 2³¹−1.
2. **Type** — 4 letras ASCII que identifican el tipo de chunk (por ejemplo `IHDR`, `tEXt`, `eXIf`).
3. **Data** — el contenido real, de longitud variable (puede ser 0).
4. **CRC** — un *checksum* de 4 bytes para detectar errores de transmisión.

### Ejemplo real

El chunk `tEXt` con el texto "Autor: José" se vería (simplificado) así:

```
Length = 0x0C (12)          → 00 00 00 0C
Type   = "tEXt"             → 74 45 58 74
Data   = "Autor\0José"      → 41 75 74 6F 72 00 4A 6F 73 E9 ...
CRC    = (cálculo)          → XX XX XX XX
```

## El CRC: el guardia de integridad

El **CRC** (*Cyclic Redundancy Check*) es un número que se calcula a partir de `Type + Data`. Si un solo bit se corrompe durante la transmisión, el CRC recalculado no coincidirá con el guardado.

PNGMetadata tiene **dos modos**:

| Modo | Comportamiento | Cuándo usarlo |
|---|---|---|
| **Lenient** (por defecto) | No verifica CRC | Uso normal: más rápido, tolera archivos con CRC defectuoso |
| **Strict** | Verifica CRC y orden de chunks | Cuando necesitas validar la integridad del archivo |

```php
// Modo estricto: valida CRC de cada chunk + reglas de orden
$documento = PngMetadataReader::create(strict: true)->read('foto.png');
```

## Las "banderas" ocultas en el tipo

Los 4 bytes del `Type` no son arbitrarios. El **bit 5** de cada letra (su mayúscula/minúscula) codifica cuatro propiedades:

| Posición | Letra mayúscula | Letra minúscula |
|---|---|---|
| 1ª letra | **Critical** (crítico): obligatorio de entender | **Ancillary** (auxiliar): se puede ignorar |
| 2ª letra | **Public** (público): definido en la spec | **Private** (privado): de uso particular |
| 3ª letra | Correcto (reservado, debe ser mayúscula) | Invalida el chunk en streams conformes |
| 4ª letra | **Unsafe to copy** (no seguro de copiar) | **Safe to copy** (seguro de copiar) |

Ejemplos:

- `IHDR` — **C**rítico, **P**úblico, reservado correcto, **U**nsafe. Es el header obligatorio.
- `tEXt` — **a**ncillary, **p**úblico, correcto, **s**afe-to-copy (minúscula). Texto seguro de copiar.
- `vpAg` — ancillary, **p**rivate (de Virtual Pagination), correcto, safe. Un chunk privado de software concreto.

### ¿Por qué importa esto?

Porque permite a la librería **clasificar cualquier chunk, incluso los que no conoce**. Un chunk desconocido sigue siendo legible en cuanto a sus propiedades: ¿es crítico?, ¿es privado?, ¿es seguro de copiar?

En el código, esto lo expone la clase `ChunkType`:

```php
$chunk->type->isAncillary();  // true para "tEXt", false para "IHDR"
$chunk->type->isPrivate();    // true para "vpAg"
$chunk->type->isSafeToCopy(); // true para "tEXt"
```

## Chunks críticos vs. auxiliares

PNG define **4 chunks críticos** que todo decodificador debe entender:

| Chunk | Función |
|---|---|
| `IHDR` | Cabecera: dimensiones, tipo de color, compresión |
| `PLTE` | Paleta de colores (imágenes indexadas) |
| `IDAT` | Los datos de imagen comprimidos |
| `IEND` | Marca el final del archivo |

Todos los demás son **auxiliares** (ancillary): un lector puede ignorarlos y aun así mostrar la imagen. **Casi todos los metadatos viven en chunks auxiliares** — precisamente los que le interesan a PNGMetadata.

## Cómo los recorre la librería

El flujo de lectura (clase `PngReader`) es:

1. Verifica la firma de 8 bytes.
2. Lee el header (8 bytes) del primer chunk.
3. Si es `IEND`, termina.
4. Lee `Length`, `Type`, salta `Data` y `CRC`.
5. Genera un objeto `RawChunk` y vuelve al paso 2.

::: tip Rendimiento: carga perezosa
`RawChunk::data()` no lee el contenido hasta que se le pide. Por eso recorrer una imagen gigante (con `IDAT` de 50 MB) cuesta memoria proporcional a los **headers** de los chunks, no al tamaño de la imagen. El `IDAT` nunca se carga en memoria.
:::

## Siguiente

Ya entiendes el formato. Veamos cómo está organizado el código que lo lee: [Arquitectura del proyecto](/es/architecture/README.html).
