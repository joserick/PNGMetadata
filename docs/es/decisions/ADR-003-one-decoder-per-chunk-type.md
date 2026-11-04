---
title: ADR-003 Un decodificador por tipo de chunk
lang: es-ES
---

# ADR-003: Un decodificador por tipo de chunk

## Status
Accepted

## Date
2026-08-12 (registrado 2026-11-03)

## Context

v1 ejecutaba seis extractores inline en la clase lectora, así que soportar un chunk
significaba editar la clase que además hacía framing del stream, y el trabajo de chunks de
Fase 2/3 (zTXt, iCCP, gAMA, cICP, APNG…) no haría más que agrandar ese archivo. Además
algunos chunks no pueden interpretarse en aislamiento: `bKGD` ocupa 1, 2 o 6 bytes según el
color type del IHDR, de modo que el orden de decodificación y el estado compartido importan.

## Decision

Una clase por tipo de chunk detrás de `ChunkDecoderInterface` (`supports()` +
`decode(RawChunk, DecodingContext, MetadataBuilder)`), reunidas por `ChunkDecoderRegistry`,
cuyo `defaults()` registra `IhdrDecoder`, `TextDecoder`, `ItxtDecoder`, `ExifDecoder`,
`SrgbDecoder` y `BkgdDecoder`. Dos reglas de soporte:

- `DecodingContext` lleva lo que los decodificadores posteriores necesitan: el `HeaderData`
  decodificado (`requireHeader()`), el flag de modo estricto del stream y si ya se vieron
  datos de imagen. `IhdrDecoder` escribe en él; `BkgdDecoder` (y luego `tRNS`) leen de él.
- `MetadataBuilder` es el único acumulador: los decodificadores nunca se hablan entre sí. Un
  chunk que no es XMP por tipo sino XMP por keyword (`XML:com.adobe.xmp` dentro de un
  `iTXt`) sigue siendo un método de `ItxtDecoder`, que delega en el helper `XmpDecoder` — no
  es una entrada del registry, porque el tipo de chunk que decodifica ya está registrado.

## Alternatives Considered

- **Un `match` sobre el tipo de chunk dentro del lector.** Descartado: recrea el monolito un
  nivel más abajo y pone la política por chunk junto al framing.
- **Que los decodificadores devuelvan valores y el lector los ensamble.** Descartado: el
  lector terminaría siendo dueño de las reglas de merge (duplicados, orden, first-wins), que
  es justo lo que `MetadataBuilder` aísla.
- **Inyectar el header por constructor en cada decodificador.** Descartado: los decodificadores
  no tienen estado y se reutilizan entre archivos; el estado por archivo pertenece al context.

## Consequences

- Añadir un chunk es una clase más una línea del registry; nada más en el lector cambia.
- Los tipos de chunk desconocidos no cuestan nada: nunca se decodifican y quedan disponibles
  como raw chunks con sus flags de propiedad del spec (`ChunkType`, spec §5.4).
- El orden de interpretación ahora es estructural: un decodificador registrado antes de `IHDR`
  no encontraría contexto, así que la superficie de builder/context es deliberadamente estrecha
  y `requireHeader()` falla como excepción de dominio en lugar de crash.
- El comportamiento por chunk vive en archivos por chunk, que es donde aterriza el trabajo de
  Fase 2.

## Sources

`modernize-architecture` design D2 y D7 (chunk bible); AGENTS.md §5;
`src/Chunk/` (`BkgdDecoder`, `ChunkDecoderInterface`, `ChunkDecoderRegistry`,
`DecodingContext`, `ExifDecoder`, `IhdrDecoder`, `ItxtDecoder`, `MetadataBuilder`,
`SrgbDecoder`, `TextDecoder`, `XmpDecoder`).
