---
title: ADR-006 Taxonomía tipada de excepciones
lang: es-ES
---

# ADR-006: Taxonomía tipada de excepciones

## Status
Accepted

## Date
2026-08-12 (registrado 2026-11-03)

## Context

v1 señalaba todos los fallos con `InvalidArgumentException` más un número mágico (101, 102,
103, 104) cuyo significado vivía solo en el código de la fachada, y lo mezclaba con warnings
crudos de PHP y `TypeError` de los extractores. El consumidor no podía distinguir "este
archivo no es un PNG" de "un chunk está corrupto" sin leer el fuente de v1, y los números no
se podían documentar con honestidad porque dos fallos distintos compartían semántica de código.

## Decision

Cada fallo es una clase bajo `PNGMetadata\Exception\` que implementa la interfaz marcador
`PngMetadataException` (que extiende `\Throwable`), de modo que un solo `catch` sigue
cubriendo la librería:

| Clase | Significado | Base |
|---|---|---|
| `InvalidPngException` | la entrada no es un PNG / entrada inservible | `\InvalidArgumentException` |
| `TruncatedDataException` | el stream terminó antes de los bytes declarados | `\RuntimeException` |
| `CrcMismatchException` | el CRC del chunk no coincide (modo estricto) | `\RuntimeException` |
| `ChunkDecodingException` | el payload de un chunk violó el spec (modo estricto) | `\RuntimeException` |

El rechazo de entrada extiende `\InvalidArgumentException` porque *sí* es un problema del
argumento del llamador; todo lo posterior al framing extiende `\RuntimeException` porque el
llamador no hizo nada mal. No hay códigos numéricos: `getCode()` no forma parte del contrato,
y el mensaje lleva la evidencia (tipo de chunk y offset en bytes — ver ADR-007).

## Alternatives Considered

- **Una excepción genérica con códigos de error.** Descartado: mantiene vivos los números
  mágicos y obliga a matching de strings para saber qué falló.
- **Lanzar solo excepciones SPL estándar.** Descartado: el consumidor no podría capturar
  "cualquier error de la librería" sin tragarse también `\RuntimeException`s ajenos.
- **Conservar los códigos 101–104 de v1 en las clases tipadas** (`modernize-architecture`
  D5). Superado por el ADR-009: los códigos pertenecían al contrato de la fachada, y la
  fachada ya no existe.

## Consequences

- El vocabulario de fallos es greppable y documentable; `api.md` lista las cuatro clases como
  contrato de error.
- Los tests afirman sobre tipos (`expectException(ChunkDecodingException::class)`), no sobre
  mensajes ni códigos, así que el redactado puede mejorar sin romper consumidores.
- La frontera del modo `strict` se expresa en un solo lugar: solo las violaciones que el modo
  puede perdonar usan `ChunkDecodingException`/`CrcMismatchException` (ADR-007).
- Quien añada un tipo de fallo debe mantenerlo bajo la interfaz, que AGENTS.md §4 enuncia
  como contrato de error del paquete.

## Sources

`modernize-architecture` design D5; `remove-v1-compatibility` design D4 (códigos fuera);
AGENTS.md §4; `src/Exception/`.
