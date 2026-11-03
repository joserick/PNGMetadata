---
title: El formato binario
lang: es-ES
---

# El formato binario: bytes, bits y endianness

Para entender cómo se extraen los metadatos necesitas dominar tres conceptos: el **byte**, el **orden de bytes** (*endianness*) y cómo se **empaquetan** los números dentro de un archivo.

## Byte y bit

La unidad mínima de un archivo es el **byte**: 8 bits, capaz de representar 256 valores (de 0 a 255).

```
Un byte = 8 bits = 0000 0000 a 1111 1111 = 0 a 255
```

Cuando necesitas guardar un número mayor que 255, usas **varios bytes consecutivos**.

## Cómo se guardan los números: "big-endian"

PNG guarda todos sus números de varios bytes en orden **big-endian** (también llamado *network byte order*): **el byte más significativo va primero**.

Imagina el número `800` en binario. En 2 bytes se escribe `0000 0011 0010 0000`:

```
Big-endian (lo que usa PNG):  [0000 0011] [0010 0000]  →  03 20
Little-endian (NO usa PNG):   [0010 0000] [0000 0011]  →  20 03
```

`0x0320 = 800`. Como ves, el mismo número ocupa los mismos bytes pero **en orden inverso** según la convención.

### ¿Por qué importa?

Porque al leer un PNG hay que reconstruir los números. Si lees los bytes `03 20` y los interpretas en el orden equivocado, obtienes `0x2003 = 8195` en vez de `800`. Un error clásico y silencioso.

## Los tipos de número que usa PNG

| Tipo | Bytes | Rango | En PHP (`unpack`) |
|---|---|---|---|
| Byte (uint8) | 1 | 0–255 | `C` |
| Entero sin signo de 16 bits | 2 | 0–65535 | `n` (big-endian) |
| Entero sin signo de 32 bits | 4 | 0–4.294.967.295 | `N` (big-endian) |

Ejemplo real del chunk IHDR: guarda el ancho de la imagen como un entero de 4 bytes. Para una imagen de `800` píxeles:

```
Bytes en el archivo:  00 00 03 20
            unpack:  N  →  800
```

## La firma: 8 bytes mágicos

Todo PNG empieza con **8 bytes fijos** que lo identifican de forma inequívoca:

```
89 50 4E 47 0D 0A 1A 0A
```

En ASCII parcialmente legible se ve `\x89PNG\r\n\x1a\n`. Esos bytes son deliberadamente "raros" para:

- Detectar que el archivo es un PNG (no un JPEG, GIF, etc.).
- Detectar corrupción si el archivo fue transferido en modo texto (los bytes `0D 0A` y `1A` se corromperían).

PNGMetadata verifica esta firma antes de hacer cualquier otra cosa. Si no coincide, lanza una excepción de dominio (`InvalidPngException`).

## Empaquetar y desempaquetar

El verbo técnico es **pack/unpack** (empaquetar/desempaquetar). En PHP, `unpack()` convierte bytes crudos en variables:

```php
// 4 bytes big-endian → entero sin signo de 32 bits
$ancho = unpack('N', "\x00\x00\x03\x20")[1]; // 800

// 2 bytes big-endian → entero de 16 bits
$valor = unpack('n', "\x03\x20")[1]; // 800
```

::: tip Nota técnica
`unpack()` devuelve un array **indexado desde 1**, no desde 0. Por eso se accede con `[1]`. Es una peculiaridad histórica de PHP.
:::

## Por qué todo esto importa para los metadatos

Cada chunk de metadatos es, en el fondo, una secuencia de bytes con un significado pactado. Por ejemplo:

- El chunk `sRGB` es **1 byte** que codifica la "intención de renderizado" (0=perceptual, 1=colorimétrico relativo...).
- El chunk `tIME` son **7 bytes**: año (2), mes, día, hora, minuto, segundo (1 cada uno).
- El chunk `cLLI` son **8 bytes**: dos enteros de 4 bytes que miden la luminancia.

Saber leer estos bytes es exactamente lo que hace la capa de parsing de la librería.

## Siguiente

Con los bytes dominados, veamos cómo se estructura cada bloque: [Anatomía de un chunk](/es/introduction/chunk-anatomy.html).
