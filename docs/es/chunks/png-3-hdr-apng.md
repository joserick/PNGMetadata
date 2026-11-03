---
title: PNG 3.0 — HDR y APNG
lang: es-ES
---

# PNG 3.0: HDR (cICP, mDCV, cLLI) y APNG

La **Tercera Edición** de la especificación PNG (W3C, junio 2025) añadió soporte oficial para **imágenes HDR** (alto rango dinámico) y para **animaciones** (APNG).

::: info Estado
Todo lo de esta página está planificado para la **Fase 3**. Es el diferenciador futuro de PNGMetadata: casi ninguna librería PHP soporta estos chunks todavía.
:::

## ¿Qué es HDR?

**HDR** (*High Dynamic Range*) es un formato de imagen capaz de representar un rango de brillo mucho mayor que el SDR tradicional (5–8 *stops*). PNG soporta dos funciones de transferencia HDR:

- **PQ** (Perceptual Quantizer, ST 2084) — sistema absoluto, basado en la pantalla.
- **HLG** (Hybrid Log-Gamma) — sistema relativo, retrocompatible con SDR.

HDR necesita señalizar tres cosas: qué primarios usa, qué función de transferencia, y qué tan brillante es el contenido. Para eso existen los tres chunks nuevos.

## cICP — code points independientes de codificación

```
Bytes:  [color primaries (1)] [transfer function (1)] [matrix coefficients (1)] [full range flag (1)]
```

Usa los *code points* del estándar **ITU-T H.273** para identificar la señal de video/color.

| Campo | Significado |
|---|---|
| Color primaries | Primarios del espacio de color (ej. 9 = BT.2020) |
| Transfer function | Función de transferencia (ej. 16 = PQ, 18 = HLG) |
| Matrix coefficients | Matriz de color — en PNG **siempre 0** (RGB) |
| Full range flag | 1 = full-range, 0 = narrow-range |

### Ejemplos normativos (bytes hex)

| Bytes | Significado |
|---|---|
| `09 10 00 01` | **BT.2100 PQ** (HDR10), full-range |
| `09 12 00 01` | **BT.2100 HLG**, full-range |
| `01 01 00 00` | BT.709 narrow-range (video) |
| `0C 0D 00 01` | **Display P3**, full-range |

::: tip Dato clave
`cICP` es el chunk de color con **máxima precedencia** (prioridad 1). Si está presente, anula a `iCCP`, `sRGB` y `gAMA`/`cHRM`.
:::

## mDCV — Volumen de color del display de mastering

```
Bytes:  24 en total
        [primaries R (x,y uint16)] [G (x,y)] [B (x,y)]  → 12 bytes, divisor 0.00002
        [white point (x,y uint16)]                        → 4 bytes, divisor 0.00002
        [max luminance (uint32)]                          → 4 bytes, divisor 0.0001 cd/m²
        [min luminance (uint32)]                          → 4 bytes, divisor 0.0001 cd/m²
```

Describe el **display usado para masterizar** el contenido (estándar SMPTE ST 2086). Permite al display del espectador optimizar su *tone mapping*.

::: details Curiosidad de orden
La spec ordena los primarios así: "el de mayor x primero, luego el de mayor y, luego el restante". Para RGB, eso coincide con R, G, B.
:::

## cLLI — Niveles de luz del contenido

```
Bytes:  [MaxCLL (uint32, ÷0.0001 cd/m²)] [MaxFALL (uint32, ÷0.0001 cd/m²)]
```

- **MaxCLL** (*Maximum Content Light Level*): el brillo del píxel más brillante.
- **MaxFALL** (*Maximum Frame-Average Light Level*): el brillo medio del frame más brillante.

Ejemplo normativo: `1000 cd/m²` se guarda como `10000000` → bytes `00 98 96 80`.

### El trío HDR10

```
cICP  +  mDCV  +  cLLI  =  señalización completa de HDR10
```

Los tres juntos describen exactamente cómo debe verse una imagen HDR en un display compatible.

## APNG — animaciones

APNG (*Animated PNG*) ahora es parte oficial de la spec. Añade tres chunks:

| Chunk | Contenido |
|---|---|
| `acTL` | Control: número de frames y de reproducciones (8 bytes) |
| `fcTL` | Control por frame: dimensiones, posición, retardo, disposición (26 bytes) |
| `fdAT` | Datos de cada frame (como IDAT pero con número de secuencia) |

PNGMetadata podrá resumir la animación **sin decodificar los píxeles**: número de frames, duración total y bucle, a partir de `acTL` y los `fcTL`.

::: tip Media type
PNG 3.0 define también el media type **`image/apng`** para servirlas correctamente por HTTP.
:::

## Reglas de orden

Los chunks `cICP` y `mDCV` **deben** aparecer antes de `PLTE` e `IDAT`. El modo estricto de PNGMetadata validará estas reglas.

## Siguiente

Con esto concluye el recorrido por los chunks. Repasa los términos en el [Glosario](/es/glossary.html).
