---
title: ADR-005 Sin GD: miniaturas como bytes
lang: es-ES
---

# ADR-005: Sin GD: miniaturas como bytes

## Status
Accepted

## Date
2026-11-02 (registrado 2026-11-03)

## Context

La primera iteración de v2 conservaba GD como dependencia *opcional*: el core tipado devolvía
la miniatura EXIF embebida como string binario y la fachada v1 la convertía a `\GdImage` para
`getThumbnail()`, con `ext-gd` declarado en `suggest` de composer (supera la postura "GD
opcional" de `modernize-architecture` D4). Una vez que el ADR-009 eliminó la fachada, esa
dependencia opcional ya no tenía llamador — pero quedaba abierta la pregunta de si el core
debía ofrecer un helper de render, y GD no viene en las imágenes de PHP mínimas.

## Decision

GD sale del paquete por completo. El core tipado es toda la historia:
`MetadataDocument::thumbnailBytes()` devuelve la miniatura embebida como bytes (leídos por la
misma frontera `ext-exif` que el perfil), y el render pertenece al consumidor —
`\imagecreatefromstring()` o el decodificador que ya use. No hay entrada `suggest`, no hay
`GdImage` en ninguna firma y no hay rama de ejecución según la extensión.

## Alternatives Considered

- **Conservar un helper `ThumbnailRenderer` que devuelva `\GdImage`.** Descartado: render es
  presentación, no extracción; el paquete Laravel de Fase 4 es el dueño natural de esa
  comodidad.
- **Devolver un `GdImage` cuando la extensión está cargada y bytes en caso contrario.**
  Descartado: dos tipos de retorno según el php.ini del host es exactamente el tipo de API
  condicional que obliga al consumidor a escribir detección de funcionalidades.
- **Devolver el payload `eXIf` crudo y que el consumidor saque la miniatura.** Descartado:
  esos bytes ya viven dentro de una estructura TIFF que la library sabe leer; obligar a
  reimplementarlo es lo opuesto al valor que ofrece el paquete.

## Consequences

- `ext-gd` no aparece en ninguna parte: ni en `require`, ni en `suggest`, ni en la imagen de
  desarrollo (el `Dockerfile` instala solo `exif`). El paquete se instala en imágenes sin GD.
- Los tests afirman sobre *bytes* de miniatura y no sobre un recurso, así que las lanes de
  performance y unit no necesitan stack gráfico.
- Quien quiere un recurso paga una llamada de función — un traspaso aceptable, documentado en
  la guía de migración v1→v2.
- Un helper de render futuro, si llega, tiene que vivir fuera de este paquete.

## Sources

`remove-v1-compatibility` design D2; `modernize-architecture` design D4 (superado);
AGENTS.md §2; `src/Metadata/MetadataDocument.php`; `composer.json`; `Dockerfile`.
