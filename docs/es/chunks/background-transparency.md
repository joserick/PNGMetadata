---
title: Fondo y transparencia — bKGD, tRNS
lang: es-ES
---

# Fondo y transparencia: bKGD y tRNS

Estos dos chunks se interpretan **en función del tipo de color** declarado en IHDR. Son el ejemplo perfecto de por qué IHDR es el "contexto" del resto.

## bKGD — color de fondo

El chunk `bKGD` (*background*) sugiere un color de fondo para cuando la imagen se muestre sobre un visor sin fondo propio (por ejemplo, un visualizador que pinta gris).

### La estructura depende del color type

| Color type | Bytes | Contenido |
|---|---|---|
| Palette (3) | 1 | Índice a la paleta |
| Grayscale (0, 4) | 2 | Muestra de gris (uint16) |
| RGB (2, 6) | 6 | Tres muestras: R, G, B (uint16 cada una) |

Ejemplo con imagen truecolor (color type 2):

```
Bytes:  00 00 | FF FF | 00 00
        └red─┘ └green┘ └blue─┘
        0       65535    0      → verde puro
```

### Por qué importa el contexto IHDR

Si lees 6 bytes sin saber el color type, no puedes distinguir "RGB" de "3 muestras de gris". El decoder `BkgdDecoder` consulta el `DecodingContext`:

```php
$header = $context->requireHeader(); // el IHDR ya decodificado

match ($header->colorType) {
    ColorType::Palette  => /* 1 byte → índice */,
    ColorType::Grayscale, ColorType::GrayscaleWithAlpha => /* 2 bytes → gris */,
    ColorType::RGB, ColorType::RGBWithAlpha => /* 6 bytes → RGB */,
};
```

```php
// Uso
$documento->background; // BackgroundColor
$documento->background?->toString(); // "65535 0 32768"
```

::: tip La v1 lo hacía "a ojo"
La v1 adivinaba el formato con `strlen($chunk) < 2`. Funcionaba para la mayoría de casos, pero mezclaba conceptualmente gris y RGB. La v2 usa el contexto explícito.
:::

## tRNS — transparencia

El chunk `tRNS` (*transparency*) permite definir transparencia cuando el tipo de color **no** incluye canal alfa (es decir, tipos 0, 2 y 3; está prohibido en 4 y 6, que ya tienen alfa).

| Color type | Contenido |
|---|---|
| Palette (3) | Un byte alfa por cada entrada de la paleta |
| Grayscale (0) | Una muestra de gris (2 bytes) que será transparente |
| RGB (2) | Una muestra RGB (6 bytes) que será transparente |

La idea: en vez de guardar un canal alfa completo (que duplica el tamaño), se designa **un color como "transparente"**.

::: info Estado
`tRNS` está planificado para la Fase 2. Al igual que `bKGD`, requiere interpretación según el color type.
:::

## Siguiente

Ya cubrimos el grueso de metadatos. Los chunks **misceláneos** (fecha, DPI, paleta) cierran el panorama clásico: [Misceláneos](/es/chunks/miscellaneous.html).
