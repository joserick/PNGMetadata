---
title: ADR-009 Compatibilidad v1 eliminada de raíz
lang: es-ES
---

# ADR-009: Compatibilidad v1 eliminada de raíz

## Status
Accepted

## Date
2026-11-02 (registrado 2026-11-03)

## Context

La primera iteración de v2 conservaba `PNGMetadata` (una fachada `ArrayObject` de ~240 líneas)
y `LegacyArrayFormatter` encima del core tipado únicamente como superficie de compatibilidad,
con tests dorados congelando la salida byte-a-byte de v1 — incluidos sus quirks, como que el
grupo IHDR omitiera `Interlace` en silencio. v2.0.0 seguía sin publicar, `1.x` seguía existiendo
para PHP legacy (7.4/8.0), y el único consumidor de la fachada era la comodidad de migración.

## Decision

Eliminar la fachada y el formatter en 2.0.0, sin release de deprecación. Lo que sale: herencia
de `ArrayObject`, `extract()` (null ante fallo), la forma agrupada legacy de `toArray()`, el
acceso por ruta `get('a:b:c')`, el render de dos columnas de `__toString()`, `getThumbnail()`
(`\GdImage`), `isPNG()`, `getType()` y los códigos numéricos 101/102/103. Lo que los reemplaza
es la API tipada (`PngMetadataReader::create()->read()` → `MetadataDocument`) o, para
presentación, una receta documentada en la tabla de migración del README. `ext-gd` desaparece
del paquete por completo (ADR-005) y la taxonomía tipada pasa a ser el único contrato de error
(ADR-006). La rama `1.x` sigue siendo la casa de la API antigua, con solo fixes de seguridad.

## Alternatives Considered

- **Publicar v2 con la fachada `@deprecated` y quitarla en v3.** Descartado: gastaría un
  release entero repitiendo "el siguiente major te rompe igual", mantendría vivos los tests de
  paridad y el quirk lock un major más, y v2 nunca vendió la fachada como su dirección de API.
- **Portar `get('a:b:c')` como getter de rutas genérico en `MetadataDocument`.** Descartado:
  reintroduce acceso stringly-typed sobre un modelo tipado — exactamente por qué v1 era difícil
  de consumir con seguridad.
- **Conservar un helper de render para miniaturas.** Descartado: presentación no es extracción
  (ver ADR-005).
- **Dejar que los tests de paridad sigan afirmando la forma legacy.** Descartado: el contrato
  que congelaban ya no existe; los casos que ejercían comportamiento del core a través de la
  fachada (rechazo de firma, tEXt repetidos, bytes de miniatura) se movieron primero a tests de
  la API tipada.

## Consequences

- Una sola API pública que documentar, versionar y soportar; todos los idiomas de docs y el
  CHANGELOG cuentan la misma historia en el mismo change.
- El consumidor de v1 que quiere la forma antigua migra deliberadamente (tabla del README) o se
  queda en `^1.0`; nada queda a medio funcionar.
- La librería suelta el quirk lock: el documento tipado siempre expone
  `header->interlaceMethod`, lo cual es un *cambio* de salida frente a v1 y está listado como
  BREAKING en el CHANGELOG.
- Los majors futuros pierden el patrón "fachada como amortiguador"; si el paquete Laravel de
  Fase 4 quiere acceso array, vive ahí.
- `openspec/specs/legacy-compatibility/` se retiró al archivar: la capability ya no tiene
  requisitos que sostener.

## Sources

`remove-v1-compatibility` design D1–D4, D6, D7; README "Upgrading from v1"; CHANGELOG 2.0.0
`Removed`; AGENTS.md §4 y §10.
