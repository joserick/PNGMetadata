---
title: Chunks y metadatos
lang: es-ES
---

# Chunks y metadatos

Esta sección es el corazón de la documentación: **cada tipo de chunk, qué significa y cómo se extrae**.

## Tabla general de chunks

| Chunk | Significado | ¿Lo soporta PNGMetadata v2? | Página |
|---|---|---|---|
| `IHDR` | Cabecera: dimensiones, tipo de color | ✅ | [IHDR](/es/chunks/ihdr.html) |
| `tEXt` | Texto simple (Latin-1) | ✅ | [Texto](/es/chunks/text.html) |
| `zTXt` | Texto comprimido | ✅ | [Texto](/es/chunks/text.html) |
| `iTXt` | Texto internacional (UTF-8) | ✅ | [Texto](/es/chunks/text.html) |
| `eXIf` | Datos EXIF (cámara) | ✅ | [EXIF](/es/chunks/exif.html) |
| XMP (en `iTXt`) | Metadatos estructurados | ✅ | [XMP](/es/chunks/xmp.html) |
| `sRGB` | Intención de renderizado | ✅ | [Color](/es/chunks/color.html) |
| `iCCP` | Perfil de color ICC | ⏳ Fase 2 | [Color](/es/chunks/color.html) |
| `gAMA` | Valor gamma | ⏳ Fase 2 | [Color](/es/chunks/color.html) |
| `cHRM` | Crominancias primarias | ⏳ Fase 2 | [Color](/es/chunks/color.html) |
| `cICP` | Señalización de color HDR | ⏳ Fase 3 | [PNG 3.0 / HDR](/es/chunks/png-3-hdr-apng.html) |
| `mDCV` | Volumen de color del display | ⏳ Fase 3 | [PNG 3.0 / HDR](/es/chunks/png-3-hdr-apng.html) |
| `cLLI` | Niveles de luz del contenido | ⏳ Fase 3 | [PNG 3.0 / HDR](/es/chunks/png-3-hdr-apng.html) |
| `bKGD` | Color de fondo | ✅ | [Fondo y transparencia](/es/chunks/background-transparency.html) |
| `tRNS` | Transparencia | ⏳ Fase 2 | [Fondo y transparencia](/es/chunks/background-transparency.html) |
| `tIME` | Fecha de modificación | ⏳ Fase 2 | [Misceláneos](/es/chunks/miscellaneous.html) |
| `pHYs` | Dimensiones físicas (DPI) | ⏳ Fase 2 | [Misceláneos](/es/chunks/miscellaneous.html) |
| `sBIT` | Bits significativos | ⏳ Fase 2 | [Misceláneos](/es/chunks/miscellaneous.html) |
| `PLTE` | Paleta de colores | ⏳ Fase 2 | [Misceláneos](/es/chunks/miscellaneous.html) |
| `acTL`/`fcTL`/`fdAT` | Animación (APNG) | ⏳ Fase 3 | [PNG 3.0 / HDR](/es/chunks/png-3-hdr-apng.html) |

**Leyenda:** ✅ soportado ahora · ⏳ planificado (ver [roadmap](/es/glossary.html#roadmap)).

## Las familias de metadatos

Los chunks se agrupan en familias según el tipo de información:

```
Metadatos PNG
├── Descriptivos (texto legible)
│   ├── tEXt  → título, autor, copyright...
│   ├── zTXt  → lo mismo pero comprimido
│   └── iTXt  → texto internacional + XMP
├── De cámara (EXIF)
│   └── eXIf  → apertura, velocidad, ISO, fecha...
├── Estructurados (XML)
│   └── XMP (dentro de iTXt) → Dublin Core, Photoshop...
├── De color
│   ├── sRGB, iCCP, gAMA, cHRM  (color SDR)
│   └── cICP, mDCV, cLLI        (color HDR, PNG 3.0)
├── De imagen
│   ├── IHDR  → dimensiones y formato
│   ├── bKGD  → color de fondo
│   ├── tRNS  → transparencia
│   └── PLTE  → paleta
└── Utilitarios
    ├── tIME  → fecha
    ├── pHYs  → DPI
    └── sBIT  → precisión de color
```

## Cómo leer las páginas de chunks

Cada página de chunk sigue la misma estructura:

1. **Qué es** — el concepto en lenguaje llano.
2. **Estructura binaria** — tabla con los campos y sus bytes.
3. **Cómo se extrae** — la explicación técnica y qué decoder del proyecto lo hace.
4. **Ejemplo** — un caso real con los bytes.

Empieza por [IHDR](/es/chunks/ihdr.html), el chunk que define cómo leer todos los demás.
