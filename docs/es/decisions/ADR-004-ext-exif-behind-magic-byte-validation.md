---
title: ADR-004 ext-exif tras la validación de magic bytes
lang: es-ES
---

# ADR-004: `ext-exif` tras la validación de magic bytes

## Status
Accepted

## Date
2026-08-12 (registrado 2026-11-03)

## Context

El chunk `eXIf` contiene una estructura TIFF — los mismos bytes que llevaría un segmento
APP1 de JPEG, salvo el código de identificación `Exif\0\0`. PHP ya la decodifica con
`exif_read_data()`, pero v1 llamaba la extensión directamente sobre el payload del chunk, así
que un perfil malformado aparecía como un warning de PHP o un `TypeError` derivado de un
retorno `false`, y un solo chunk malo podía abortar la lectura.

## Decision

Conservar la extensión y ponerle delante una puerta de validación. `ExifDecoder` comprueba
los cuatro magic bytes de TIFF (`II\x2A\x00` little-endian, `MM\x00\x2A` big-endian —
spec §11.3.4.5.2) antes de tocar la extensión y luego alimenta el payload a
`exif_read_data()` a través de un stream `php://temp`. Una secuencia inválida o una
decodificación fallida lanza `ChunkDecodingException`
(`invalid TIFF header: expected "II*\0" or "MM\0*" magic bytes`), cuyo tratamiento decide el
modo (ADR-007).

## Alternatives Considered

- **Parser TIFF/EXIF propio para eliminar la extensión.** Descartado: mucho esfuerzo, alto
  riesgo de mal-decodificar tags de fabricante, y `ext-exif` viene en prácticamente toda
  instalación de PHP (aquí es una extensión requerida del paquete).
- **Llamar `exif_read_data()` sobre el payload crudo sin validar.** Descartado: el modo de
  fallo de la extensión es un warning suprimido más `false`, que es exactamente la corrupción
  silenciosa que mostraba v1, y el spec de PNG recomienda la comprobación.
- **Omitir EXIF y exponer solo los bytes.** Descartado: las secciones EXIF decodificadas son
  la razón principal por la que el consumidor usa esta library.

## Consequences

- El único punto donde el paquete habla con `ext-exif` para decodificar perfiles es una clase
  frontera, así que un cambio futuro a un lector TIFF en puro PHP queda contenido.
- Un perfil malformado produce una falla tipada y regida por el modo, con tipo de chunk y
  offset, en lugar de un warning — lo que mantiene significativo el `failOnWarning="true"` de
  PHPUnit.
- La salida sigue variando levemente entre versiones de PHP (comportamiento de la extensión),
  por eso la suite afirma sobre claves estables y la matriz de CI cubre 8.3/8.4/8.5.
- `thumbnailBytes()` reutiliza la misma frontera de extensión (ADR-005).

## Sources

`modernize-architecture` design D3; `unify-decode-failure-policy` design D1;
spec `metadata-extraction` ("eXIf decoding with magic-byte validation");
`src/Chunk/ExifDecoder.php`.
