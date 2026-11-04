---
title: ADR-007 Política de fallo regida por el modo
lang: es-ES
---

# ADR-007: Política de fallo regida por el modo

## Status
Accepted

## Date
2026-11-02 (registrado 2026-11-03)

## Context

Antes de que v2 se publicara, el manejo de fallos eran tres mecanismos ad-hoc que
contradecían entre sí y a las specs:

| Caso | Comportamiento entonces |
|---|---|
| IHDR malformado | throw (fatal) |
| eXIf corrupto / magic malo | omitido en silencio, en **ambos** modos |
| Paquete XMP malformado | omitido en silencio, en ambos modos |
| Payload `iTXt` malformado | throw — abortando el documento completo |
| Payload `bKGD` corto | omitido en silencio |
| `bKGD` sin contexto IHDR | crash con `\LogicException` |

Un solo `iTXt` corrupto podía destruir un archivo por lo demás legible, mientras que el modo
estricto — cuya razón de ser es reportar conformidad — guardaba silencio sobre la mayoría de
las violaciones. `strict` vivía solo en `PngReader` (framing/CRC) y nunca llegaba a la capa de
decodificación. El ancla normativa es PNG Third Edition: un chunk *ancillary* inválido nunca
es motivo para rechazar el archivo.

## Decision

Los decodificadores expresan las violaciones; el lector decide el tratamiento, en exactamente
un lugar (`PngMetadataReader::read()`):

- **Violaciones de dato inservible** (separadores inválidos, inflación fallida, magic bytes
  TIFF incorrectos, keyword inválida, byte de intent desconocido, payload corto): el
  decodificador *siempre* lanza `ChunkDecodingException::forChunk($code, $offset, $reason)`.
  Estricto relanza; leniente omite ese chunk y continúa. IHDR relanza en ambos modos — sin el
  header, los chunks siguientes pierden contexto de interpretación.
- **Violaciones de dato servible** (singleton duplicado, eXIf tras IDAT): el dato está bien y
  la regla del stream no, así que leniente igualmente debe producir el valor. Estas
  comprobaciones son conscientes del modo y leen `DecodingContext::$strict` / el flag del
  builder.
- **Fallos de nivel de stream** quedan fuera de la política: `TruncatedDataException` es fatal
  en ambos modos (tras una lectura corta el iterador ya no puede confiar en el framing), y
  `CrcMismatchException` solo puede existir donde se verifican CRC, es decir, en modo estricto.

## Alternatives Considered

- **Que cada decodificador consulte un flag de estricto y decida.** Descartado: la política se
  dispersa en N decodificadores y cada chunk de Fase 2 vuelve a implementarla; un punto único
  mantiene Open/Closed — "un chunk nuevo es un decodificador que lanza".
- **Tragar todo y loguear después.** Descartado: el modo estricto no tendría nada que reportar
  y la evidencia (tipo de chunk + offset) se perdería.
- **Fallar rápido ante cualquier chunk malformado.** Descartado: contradice la regla de chunks
  ancillary del spec y el propósito de la librería — extraer lo que hay.
- **Devolver `null` desde los decodificadores al fallar.** Descartado: pierde el motivo; `null`
  no puede decir "unknown sRGB intent byte 9 at offset 1234".

## Consequences

- `read()` leniente siempre entrega un documento salvo que IHDR o el stream sean inservibles;
  la política es ahora un único bloque `catch` que leer.
- El modo estricto es el verificador de conformidad, con mensajes de la forma
  `Decode failure in chunk "iTXt" at offset 1234: <reason>` — evidencia suficiente para
  arreglar el archivo.
- Quien necesite saber *qué se omitió* en modo leniente todavía no puede preguntarlo: no hay
  API de observabilidad, decidido como open question en lugar de media implementación.
- Los decodificadores nunca deben capturar su propia violación; hacerlo los sacaría del modo
  estricto en silencio.

## Sources

`unify-decode-failure-policy` design D1–D4, D7; spec `metadata-extraction` ("Unified
decode-failure policy"); `src/PngMetadataReader.php`;
`src/Exception/ChunkDecodingException.php`.
