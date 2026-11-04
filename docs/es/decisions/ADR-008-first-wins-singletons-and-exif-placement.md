---
title: ADR-008 Singleton first-wins y placement de eXIf
lang: es-ES
---

# ADR-008: Singleton first-wins y placement de eXIf

## Status
Accepted

## Date
2026-11-02 (registrado 2026-11-03)

## Context

Dos reglas del spec de PNG hablan de la *forma del stream* y no del contenido del payload, así
que un decodificador puede producir un valor perfecto mientras el archivo sigue sin ser
conforme:

- sRGB, bKGD, eXIf y la entrada `iTXt` de packet XMP permiten como mucho una aparición. Antes
  de unificar, eXIf tenía guard first-wins mientras sRGB, bKGD y XMP se quedaban en silencio
  con la *última* instancia — un duplicado podía cambiar la respuesta según el orden de
  decodificación.
- PNG Third Edition (§11.3.4.5) exige eXIf antes del primer IDAT. Los codificadores legacy no
  siempre obedecen, y el perfil en sí se lee perfectamente cuando llega tarde.

Estos casos no se pueden tratar como un payload malformado (ADR-007): lanzar sin condición
tiraría datos que el lector leniente existe justamente para conservar.

## Decision

Ambas reglas son **conscientes del modo** y viven donde el estado ya está:

- **Singletons**: una guarda por clave en `MetadataBuilder::acceptsSingleton($key, …)` sobre un
  mapa `$singletons`, usada por `setRenderingIntent('sRGB')`, `setBackground('bKGD')`,
  `setExif('eXIf')` y `setXmp('XMP packet')`. La primera instancia gana en ambos modos; la
  segunda se ignora en silencio en leniente y lanza
  `only one <key> chunk is allowed; the first instance wins.` en estricto. Añadir un singleton
  de Fase 2 (gAMA, cHRM, tRNS, iCCP, pHYs…) es una llamada, no otro `if` por campo.
- **Placement de eXIf**: el lector registra que hubo datos de imagen en cuanto despacha
  `IDAT`/`fdAT` (`DecodingContext::noteImageData()`), y `ExifDecoder` rechaza el chunk solo
  cuando `$context->strict && $context->sawImageData()` con
  `eXIf must appear before the first IDAT chunk (PNG Third Edition, §11.3.4.5).` En leniente lo
  decodifica.
- tEXt e iTXt no-XMP siguen repetibles — el spec los permite y las entradas se anexan en orden
  de archivo.

## Alternatives Considered

- **Rechazar duplicados en ambos modos.** Descartado: extrae de menos en modo leniente; el dato
  es usable y el archivo solo es no conforme.
- **Last-wins.** Descartado: depende del orden de iteración, es imposible de explicar, y era el
  accidente pre-unificación, no una decisión.
- **Comprobar el placement con aritmética de offsets absolutos en el builder.** Descartado: el
  hecho "ya se vieron datos de imagen" pertenece al context de posición del stream, no al
  acumulador.

## Consequences

- El conjunto de singletons es una lista de claves en un solo archivo, así que los chunks de
  Fase 2 entran con una línea cada uno y no pueden olvidar la guarda.
- El modo estricto ahora falla archivos que el leniente lee correctamente — deliberado, y la
  razón por la que ambos comportamientos se afirman en el mismo caso de test.
- Desaparece la ambigüedad por duplicado del documento: el primer valor decodificado es
  determinísticamente el que se expone.
- El consumidor leniente de un archivo con eXIf tardío obtiene EXIF que un verificador estricto
  rechaza; la diferencia está documentada (spec `metadata-extraction`) y no escondida.

## Sources

`unify-decode-failure-policy` design D5, D6; spec `metadata-extraction` ("First-wins singleton
chunks", "eXIf decoding with magic-byte validation"); `src/Chunk/MetadataBuilder.php`;
`src/Chunk/ExifDecoder.php`; `src/Chunk/DecodingContext.php`.
