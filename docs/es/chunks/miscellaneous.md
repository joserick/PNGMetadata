---
title: Chunks misceláneos — tIME, pHYs, sBIT, PLTE
lang: es-ES
---

# Chunks misceláneos: tIME, pHYs, sBIT, PLTE

Estos chunks aportan información práctica: la fecha de modificación, el tamaño físico (DPI), la precisión del color y la paleta.

::: info Estado
Todos los chunks de esta página están planificados para la Fase 2. Se documentan aquí para completar el panorama.
:::

## tIME — fecha de modificación

```
Bytes:  [year (2)] [month (1)] [day (1)] [hour (1)] [minute (1)] [second (1)]
```

Son 7 bytes que codifican la fecha en **UTC**. Ejemplo:

```
Bytes:  07 E6 | 08 | 0B | 0A | 00 | 00
        2026    08   11   10   00   00
        → 2026-08-11 10:00:00 UTC
```

Extraerlo es trivial: desempaquetar y construir un `DateTimeImmutable`. PNGMetadata lo mapeará a `$documento->lastModified`.

## pHYs — dimensiones físicas (DPI)

```
Bytes:  [pixels per unit X (4)] [pixels per unit Y (4)] [unit (1)]
```

Define la resolución física de la imagen:

- **unit = 0**: los valores son *desconocidos* (ratio de aspecto solo).
- **unit = 1**: los valores son píxeles **por metro**.

Para convertir a DPI (puntos por pulgada):

```
DPI = píxeles por metro × 0.0254
```

Ejemplo: `2835` píxeles por metro (el valor típico de 72 DPI):

```
2835 × 0.0254 = 72.009 ≈ 72 DPI
```

## sBIT — bits significativos

```
Bytes:  1 a 4 bytes, según el color type (uno por canal)
```

Indica cuántos bits de cada muestra son realmente **significativos** (no ruido). Se usa cuando una imagen de 8 bits se generó a partir de una fuente de menor precisión (por ejemplo, de 5 bits).

## PLTE — paleta de colores

```
Bytes:  hasta 768 (256 entradas × 3 bytes RGB)
```

Define la paleta de las imágenes con color type 3 (indexadas). Cada entrada es un triplete RGB de 8 bits. PNGMetadata la expondrá como lista de colores.

::: tip ¿Por qué importa la paleta a los metadatos?
Una imagen indexada solo guarda índices; sin la paleta no puedes saber qué color representa cada índice. Es información estructural, no "metadata" en sentido estricto, pero útil para cualquier herramienta que procese la imagen.
:::

## Siguiente

Llegamos a la frontera del formato: los chunks de **PNG 3.0** para HDR y animación: [PNG 3.0 / HDR](/es/chunks/png-3-hdr-apng.html).
