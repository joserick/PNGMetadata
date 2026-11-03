---
title: PNGMetadata
lang: es-ES
---

# PNGMetadata

**PNGMetadata** es una librería PHP nativa que extrae los metadatos de imágenes PNG sin usar herramientas externas como ExifTool. Trabaja leyendo el archivo a nivel de bytes para interpretar la información que hay oculta dentro de la imagen: datos EXIF, XMP, dimensiones, color, texto y más.

Esta documentación está pensada como una **guía de referencia** para entender tanto el *qué* (los metadatos) como el *cómo* (la lectura a bajo nivel de bits y bytes). No asume experiencia previa con formatos binarios.

## ¿A quién va dirigida?

- A **desarrolladores** que usan la librería y quieren entender qué datos obtienen.
- A **contribuidores** que quieren tocar el código sin perderse en la complejidad binaria.
- A cualquier persona con **curiosidad** por cómo se guarda la información dentro de un archivo.

## Cómo leer esta documentación

Sigue el orden de las secciones si eres nuevo en el tema:

1. **[Introducción](/es/introduction/README.html)** — qué es PNG y por qué su formato importa.
2. **[Arquitectura del proyecto](/es/architecture/README.html)** — cómo está organizada la librería y su flujo de datos.
3. **[Chunks y metadatos](/es/chunks/README.html)** — el corazón: cada tipo de chunk y qué significa.
4. **[Glosario](/es/glossary.html)** — referencia rápida de términos técnicos.

## Lo esencial en un minuto

Un PNG **no es una imagen** en el sentido clásico. Es un contenedor de datos organizado en bloques llamados **chunks**:

```
┌──────────────────────────────────────────────┐
│  Firma (8 bytes)  "esto es un PNG"           │
├──────────────────────────────────────────────┤
│  Chunk IHDR      dimensiones y tipo de color │
│  Chunk tEXt      texto (autor, título...)    │
│  Chunk eXIf      datos EXIF de la cámara     │
│  Chunk iTXt      XMP (metadata estructurado) │
│  Chunk IDAT      los píxeles comprimidos     │
│  Chunk IEND      fin del archivo             │
└──────────────────────────────────────────────┘
```

Cada chunk es un "sobre" con una etiqueta (su tipo, 4 letras) y un contenido (bytes). La librería **abre los sobres que le interesan** y **ignora los píxeles** (IDAT), porque solo necesita los metadatos.

::: tip Dato clave
PNGMetadata **lee** metadatos. No modifica el archivo y nunca decodifica la imagen en sí (los píxeles).
:::

## Enlaces útiles

- [Repositorio del proyecto](https://github.com/joserick/PNGMetadata)
- [Especificación PNG (W3C, Tercera Edición)](https://www.w3.org/TR/png-3/) — la fuente de verdad normativa.
