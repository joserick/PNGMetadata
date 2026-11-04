---
title: Registros de decisión arquitectónica
lang: es-ES
---

# Registros de decisión arquitectónica

El razonamiento detrás de la arquitectura de PNGMetadata: qué se decidió, por qué,
qué se descartó y qué cuesta. El código muestra *qué* hace; estos registros explican
*por qué es así*, para que la misma decisión no se vuelva a discutir en el próximo
refactor.

El contrato de comportamiento vive en las specs (`openspec/specs/`). Si un registro y
una spec difieren, **la spec es normativa** y el registro es historia.

## Índice

| ADR | Título | Estado |
|---|---|---|
| [001](/es/decisions/ADR-001-two-layers-plus-typed-entry-point.html) | Dos capas más un punto de entrada tipado | Aceptado |
| [002](/es/decisions/ADR-002-lazy-chunk-payloads.html) | Payloads de chunk bajo demanda | Aceptado |
| [003](/es/decisions/ADR-003-one-decoder-per-chunk-type.html) | Un decodificador por tipo de chunk | Aceptado |
| [004](/es/decisions/ADR-004-ext-exif-behind-magic-byte-validation.html) | `ext-exif` tras la validación de magic bytes | Aceptado |
| [005](/es/decisions/ADR-005-no-gd-thumbnails-as-bytes.html) | Sin GD: miniaturas como bytes | Aceptado |
| [006](/es/decisions/ADR-006-typed-exception-taxonomy.html) | Taxonomía tipada de excepciones | Aceptado |
| [007](/es/decisions/ADR-007-mode-governed-decode-failure-policy.html) | Política de fallo regida por el modo | Aceptado |
| [008](/es/decisions/ADR-008-first-wins-singletons-and-exif-placement.html) | Singleton first-wins y placement de eXIf | Aceptado |
| [009](/es/decisions/ADR-009-v1-compatibility-removed-outright.html) | Compatibilidad v1 eliminada de raíz | Aceptado |
| [010](/es/decisions/ADR-010-test-lanes-and-ratcheted-coverage-gate.html) | Carreras de pruebas y gate de coverage | Aceptado |

## Anatomía de un registro

Todos los ADR usan las mismas secciones, en este orden:

```
# ADR-00N: <título>
## Status            Accepted | Proposed | Superseded by ADR-… | Deprecated
## Date              cuándo se tomó la decisión (y cuándo se registró, si fue después)
## Context           las fuerzas que la moldearon
## Decision          qué se eligió, en un párrafo en imperativo
## Alternatives Considered   cada opción con su motivo de descarte
## Consequences      qué facilita y qué encarece
## Sources           secciones de design, specs y código que documenta el registro
```

## Reglas de la casa

- La numeración es solo aditiva: los registros nuevos toman el siguiente número libre
  y nunca reutilizan uno retirado. Jubilar una decisión cambia su `Status`, no su número.
- Nada se borra. Cuando una decisión se reemplaza, un registro nuevo la supera y la
  antigua apunta al sucesor.
- Ambos idiomas espejan los mismos nombres de archivo. Un registro añadido en `en/`
  debe aterrizar en [`/es/decisions/`](/es/decisions/README.html) con el mismo basename.
