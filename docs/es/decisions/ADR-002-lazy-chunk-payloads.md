---
title: ADR-002 Payloads de chunk bajo demanda
lang: es-ES
---

# ADR-002: Payloads de chunk bajo demanda

## Status
Accepted

## Date
2026-08-12 (registrado 2026-11-03)

## Context

Un archivo PNG es datos de imagen con unos pocos bytes de metadata adjuntos: una captura de
50 MB puede llevar menos de 2 KB de chunks de texto, y IDAT suele ser un chunk enorme o
muchos consecutivos. Extraer metadata obliga a recorrer el archivo entero, así que el loop
de framing ingenuo —leer header, leer payload, devolver el struct— movería la imagen
completa por memoria solo para mirar sus etiquetas. La capability `png-parsing` especifica
lo contrario: los chunks de imagen son saltables.

## Decision

`RawChunk` guarda solo lo que el paso de framing ya sabe (tipo, offset absoluto, longitud
declarada, offset del payload) y materializa el payload bajo demanda en `data()`, que hace
seek al payload, lo lee y **restaura el cursor antes de devolver** — de ahí que recorrer el
stream sea neutral en posición se lea o no un payload. El lector nunca llama a `data()` para
`IDAT`/`fdAT`.

El coste de iterar un archivo es por tanto proporcional a los *headers* de chunk, no al
tamaño de la imagen.

## Alternatives Considered

- **Leer el payload durante el framing.** Descartado: `memory_get_peak_usage()` crece con la
  imagen, y lo único que hace la library es metadata.
- **Un iterador paralelo solo-metadata que salte los datos de imagen.** Descartado: dos
  caminos de código sobre el mismo stream, y habría que clasificar los chunks desconocidos
  antes de poder saltar sus payloads.
- **Mapear el archivo a memoria.** Descartado: añade comportamiento específico de plataforma
  y exige que exista un archivo, cuando el API también acepta streams y strings binarios.

## Consequences

- Los archivos grandes se extraen en memoria constante; la garantía la afirma la lane
  `performance` (`composer test:performance`), que construye un único `IDAT` de 50 MB fuera
  de la ventana medida, itera `read()` y exige que el peak quede por debajo del tamaño del
  payload más ~14 MB — es decir, que los datos de imagen nunca se filtran al loop de lectura.
- El acceso al payload es un seek, así que los decodificadores pueden llamar `data()` con
  libertad, pero cada llamada vuelve a leer. Aceptable: los chunks de metadata son pequeños y
  se decodifican una vez.
- En modo estricto, la verificación de CRC (`computeCrc()`) debe materializar cada chunk
  incluida la imagen. Trade-off aceptado: validar CRC es justo lo que significa modo
  estricto, y el usuario eligió ese coste.
- `data()` debe seguir siendo neutral en posición. Cualquier cambio futuro que deje el cursor
  en otro punto corrompe silenciosamente la iteración que sigue.

## Sources

`modernize-architecture` design D2; spec `png-parsing` ("Image data chunks are skippable");
`src/Parsing/RawChunk.php`; `tests/Unit/Parsing/LargeIdatMemoryTest.php`.
