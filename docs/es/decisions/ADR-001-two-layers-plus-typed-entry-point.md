---
title: ADR-001 Dos capas más un punto de entrada tipado
lang: es-ES
---

# ADR-001: Dos capas más un punto de entrada tipado

## Status
Accepted

## Date
2026-08-12 (registrado 2026-11-03)

## Context

v1 era una sola clase de 670 líneas (`src/PNGMetadata.php extends ArrayObject`) que
validaba la entrada, recorría el flujo de chunks y ejecutaba seis extractores hardcodeados
desde su constructor. Cada corrección tocaba los tres intereses a la vez, y la clase no
podía reutilizarse como core sin framework para el paquete Laravel planificado, que además
debía funcionar sobre streams sin acceso a disco.

## Decision

Mantener dos capas más un punto de entrada, con todas las dependencias apuntando hacia
adentro:

- **Domain** (`src/Metadata/`): value objects `final readonly` y enums. Sin I/O, sin
  conocimiento de bytes.
- **Parsing** (`src/Parsing/`, `src/Chunk/`, `src/Exception/`): la única capa que toca
  bytes — normalización del stream, framing de chunks, decodificación por tipo y fallos
  tipados.
- **`PngMetadataReader::read()`**: el único punto de entrada público, devuelve
  `MetadataDocument`.

El modelo nunca referencia al parser; el punto de entrada es el único lugar donde se
conectan.

## Alternatives Considered

- **Refactor in situ conservando una sola clase.** Descartado: el monolito es la causa raíz
  de la fragilidad que se corrige, no un accidente.
- **Publicar core y fachada como dos paquetes.** Descartado por ahora: un repo y un paquete
  son más simples; la división puede llegar si el paquete Laravel la pide.
- **Una fachada sobre el modelo tipado** (array v1, `ArrayObject`). Implementada en la
  primera iteración de v2 y eliminada después — ver ADR-009.

## Consequences

- Añadir un tipo de chunk significa añadir un decodificador (ADR-003); el modelo y el punto
  de entrada no cambian.
- El modelo de dominio se prueba sin archivos y el parser sin conocimiento de interpretación.
- Quien se incorpora necesita aprender tres directorios antes de que el código se lea solo;
  AGENTS.md §3 y la página de arquitectura por capas llevan el mapa.
- El consumidor tiene exactamente una superficie de API, lo que convierte al modelo tipado
  en la frontera de compatibilidad de todos los releases futuros.

## Sources

`modernize-architecture` design D1
(`openspec/changes/archive/2026-08-12-modernize-architecture/design.md`); AGENTS.md §3;
`src/PngMetadataReader.php`.
