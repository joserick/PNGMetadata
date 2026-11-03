---
title: ¿Qué es un PNG?
lang: es-ES
---

# ¿Qué es un PNG?

**PNG** (*Portable Network Graphics*) es un formato de imagen diseñado en 1995 como alternativa libre y sin patentes al GIF. El nombre se pronuncia "ping".

A diferencia de lo que se suele creer, PNG es mucho más que "una imagen". Es un **contenedor de datos** extremadamente flexible que puede guardar:

- La imagen en sí (los píxeles).
- **Metadatos**: texto descriptivo, datos de la cámara (EXIF), datos estructurados (XMP).
- **Información de color**: espacio de color, gamma, perfil ICC.
- **Información física**: tamaño en centímetros, DPI.
- **Animaciones** (APNG) y **HDR** (desde PNG 3.0, 2025).

PNGMetadata se interesa solo por los metadatos: los *chunks* que no son la imagen.

## ¿Por qué existe esta librería?

La mayoría de herramientas que leen metadatos dependen de un programa externo como **ExifTool** (un binario que se ejecuta aparte). Esto tiene dos inconvenientes:

1. Requiere instalar software adicional en el servidor.
2. Añade una dependencia externa frágil a tu aplicación.

PNGMetadata resuelve esto siendo **100% nativa**: lee el archivo byte a byte con PHP puro, sin procesos externos.

## La idea central: chunks

La palabra clave de PNG es **chunk** (trozo o bloque). Un archivo PNG es una lista de chunks, cada uno con:

- Una **etiqueta** de 4 letras que dice qué contiene (por ejemplo `IHDR`, `eXIf`, `tEXt`).
- Un **bloque de datos** en bytes.

Piénsalo como un tren de vagones:

```
┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐
│  IHDR   │──│  tEXt   │──│  eXIf   │──│  IDAT   │──│  IEND   │
│ (ficha) │  │ (título)│  │ (EXIF)  │  │ (píxel) │  │ (fin)   │
└─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘
```

Un lector de PNG **no necesita entender todos los vagones**: los desconocidos se saltan sin problema (cada vagón declara su longitud). Esto es lo que hace el formato tan extensible — de ahí que 30 años después se sigan añadiendo chunks nuevos (HDR en 2025) sin romper compatibilidad.

::: tip Analogía
PNGMetadata es como un inspector de tren que abre solo ciertos vagones (los de metadatos), lee su contenido y deja pasar el resto sin mirar dentro.
:::

## La especificación

PNG está gobernado por una especificación del **W3C**. La versión actual es la **Tercera Edición** (Recomendación del 24 de junio de 2025), que añade soporte oficial para HDR y animaciones.

- [Especificación PNG 3.0 (W3C)](https://www.w3.org/TR/png-3/)

Cuando haya duda sobre el significado de un chunk, la especificación es la fuente de verdad.

## Siguiente

Ya sabes *qué* es un PNG. Ahora veamos *cómo* se organizan sus datos a nivel físico: [El formato binario](/es/introduction/binary-format.html).
