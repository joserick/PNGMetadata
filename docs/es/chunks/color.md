---
title: Chunks de color — sRGB, iCCP, gAMA, cHRM
lang: es-ES
---

# Chunks de color: sRGB, iCCP, gAMA, cHRM

Estos chunks describen **cómo debe interpretarse el color** de los píxeles: qué espacio de color usan, qué gamma tienen y qué crominancias (primarios) definen su gama.

::: warning Concepto previo
Si no sabes qué es "gamma" o "primarios", pasa primero por el [glosario](/es/glossary.html). Aquí asumimos esas nociones.
:::

## La precedencia de color

PNG permite que convivan varios chunks de color, pero **uno manda**. La especificación (§4.3, Tabla 1) define una prioridad:

| Prioridad | Chunk | Descripción |
|---|---|---|
| 1 (máxima) | `cICP` | Señalización de color por code points (HDR, PNG 3.0) |
| 2 | `iCCP` | Perfil ICC completo |
| 3 | `sRGB` | Declara "esto es sRGB estándar" |
| 4 (mínima) | `cHRM` + `gAMA` | Crominancias + gamma explícitas |

> *"the chunk with the lowest Priority number should take precedence and any higher-numbered chunk types should be ignored."*

Es decir: si hay `cICP`, se ignoran los demás. Si no, `iCCP` manda sobre `sRGB`, etc.

## sRGB — intención de renderizado

El chunk `sRGB` es el más simple: **1 solo byte**.

```
Bytes:  [intención de renderizado]
```

El byte codifica la *rendering intent* (intención de renderizado), un concepto de la ICC:

| Valor | Enum (`RenderingIntent`) | Etiqueta | Significado |
|---|---|---|---|
| 0 | `Perceptual` | Perceptual | Optimizado para fotos, colores "agradables" |
| 1 | `RelativeColorimetric` | Relative Colorimetric | Colores exactos relativos al blanco |
| 2 | `Saturation` | Saturation | Optimizado para gráficos, colores vivos |
| 3 | `AbsoluteColorimetric` | Absolute Colorimetric | Colores exactos absolutos |

### Cómo se extrae

`SrgbDecoder` lee el byte y lo convierte al enum `RenderingIntent`. Si el valor está fuera de rango (4-255), lo reporta como "desconocido" en vez de fallar.

```php
$documento->renderingIntent; // RenderingIntent::Perceptual
$documento->renderingIntent?->label(); // 'Perceptual'
```

## iCCP — perfil ICC embebido

```
profile name (Latin-1) \0 | compression method (1 byte) | ICC profile (zlib comprimido)
```

Un **perfil ICC** (International Color Consortium) es un archivo binario que describe matemáticamente un espacio de color. Es el mecanismo más completo y flexible, pero también el más pesado.

::: info Estado
`iCCP` es compatible desde `2.0.0-beta.4`. `IccpDecoder` valida el nombre y el método, infla el perfil bajo el presupuesto compartido del lector y después parsea la cabecera ICC de 128 bytes, la tabla de tags y el tag de descripción `desc`/`mluc`. El documento expone nombre, descripción, versión, clase, espacio de color, PCS e intent de renderizado.
:::

```php
$documento->iccProfile; // ?IccProfile
$documento->iccProfile?->description;
```

## gAMA — gamma

```
Bytes:  [entero de 4 bytes = gamma × 100000]
```

El valor se guarda multiplicado por 100000. Por ejemplo, `45455` corresponde a un gamma de `0.45455` (que es `1/2.2`, el gamma típico de las pantallas).

```
45455 ÷ 100000 = 0.45455
```

## cHRM — crominancias primarias

```
Bytes:  8 enteros de 4 bytes, cada uno × 100000
        [white x] [white y] [red x] [red y] [green x] [green y] [blue x] [blue y]
```

Define las coordenadas cromáticas (en espacio CIE xyY) del punto blanco y de los primarios rojo, verde y azul. Junto con `gAMA`, describe exactamente el espacio de color sin necesidad de un perfil ICC.

::: info Estado
`gAMA` y `cHRM` son compatibles desde `2.0.0-beta.4`. `GamaDecoder` rechaza un payload de longitud distinta de 4 bytes o un valor cero; `ChrmDecoder` exige exactamente 32 bytes y expone el punto blanco y los primarios rojo, verde y azul.
:::

```php
$documento->gamma?->value();       // p. ej. 0.45455
$documento->chromaticities;        // ?Chromaticities
```

## Qué soporta hoy PNGMetadata v2

v2 soporta **`sRGB`, `iCCP`, `gAMA` y `cHRM`**. El documento expone cada chunk decodificado por separado y añade `colorSource` con el ganador resuelto para los chunks soportados:

```
iCCP > sRGB > cHRM+gAMA
```

`gAMA` por sí solo se ve como valor de gamma pero no produce `colorSource`, porque no define un espacio de color completo. `cICP` se sumará a la parte superior de esta tabla en la Fase 3.

## Siguiente

El color describe cómo se ve la imagen. Veamos los chunks de **fondo y transparencia**: [Fondo y transparencia](/es/chunks/background-transparency.html).
