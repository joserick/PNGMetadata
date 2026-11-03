---
title: Glosario
lang: es-ES
---

# Glosario

Referencia rápida de términos usados en esta documentación y en el código.

## Fundamentos binarios

- **Bit** — la unidad mínima de información: 0 o 1.
- **Byte / octeto** — 8 bits; un número entre 0 y 255.
- **Big-endian** — orden de bytes donde el más significativo va primero. Es el que usa PNG.
- **Little-endian** — el orden contrario (usado por Intel). El EXIF puede estar en cualquiera de los dos.
- **Pack / unpack** — empaquetar valores en bytes y desempaquetar bytes en valores. En PHP, `pack()` y `unpack()`.
- **uint8 / uint16 / uint32** — entero sin signo de 8/16/32 bits.

## Formato PNG

- **Chunk** — bloque de datos con tipo, contenido y CRC. La unidad fundamental de PNG.
- **Firma (signature)** — los 8 bytes iniciales que identifican un archivo como PNG.
- **CRC** — *Cyclic Redundancy Check*; checksum de integridad.
- **Critical chunk** — chunk obligatorio de entender (IHDR, PLTE, IDAT, IEND).
- **Ancillary chunk** — chunk auxiliar que se puede ignorar. Casi todos los metadatos viven aquí.
- **Safe-to-copy** — propiedad de un chunk: si un editor puede copiarlo tal cual al reescribir el archivo.
- **IHDR** — chunk de cabecera con dimensiones y tipo de color.

## Color

- **Color type** — cómo se organizan los canales de color (gris, RGB, paleta, con/sin alfa).
- **Bit depth** — bits por muestra de color (1–16).
- **Gamma** — exponente de la función de transferencia de potencia; controla el brillo percibido.
- **Primarios (primaries)** — los colores rojo, verde y azul de referencia que definen un espacio de color.
- **Crominancias (chromaticities)** — las coordenadas x,y de los primarios y del punto blanco en espacio CIE.
- **Punto blanco (white point)** — el color que se considera "blanco" en un espacio de color.
- **Espacio de color** — el modelo que define cómo los números se traducen en colores.
- **sRGB** — el espacio de color estándar de la web.
- **Rendering intent** — estrategia de conversión de color: perceptual, colorimétrico relativo, saturación, colorimétrico absoluto.
- **Perfil ICC** — archivo que describe matemáticamente un espacio de color.
- **Transfer function** — función que relaciona la señal con la luminancia.
- **PQ** — Perceptual Quantizer, función de transferencia HDR absoluta.
- **HLG** — Hybrid Log-Gamma, función de transferencia HDR relativa.
- **SDR / HDR** — rango dinámico estándar / alto.
- **Mastering display** — el display de referencia usado al crear el contenido.
- **MaxCLL / MaxFALL** — brillo máximo de píxel / de frame promedio.
- **Tone mapping** — adaptación del contenido a las capacidades del display.

## Metadatos

- **EXIF** — estándar de metadatos técnicos de cámara.
- **XMP** — plataforma de metadatos extensible de Adobe, basada en XML/RDF.
- **RDF** — *Resource Description Framework*; el modelo de datos del XMP.
- **Namespace** — espacio de nombres XML que agrupa términos bajo un prefijo.
- **BCP47** — estándar de *language tags* (ej. `es`, `en-GB`).
- **Latin-1 / ISO-8859-1** — codificación de caracteres de Europa occidental.
- **UTF-8** — codificación Unicode universal.
- **zlib / deflate** — algoritmo de compresión usado en PNG.
- **Dublin Core** — conjunto de metadatos estándar (`dc:` en XMP).

## Técnicas del proyecto

- **Lazy payload** — técnica de no leer el contenido de un chunk hasta que se pida, ahorrando memoria.
- **Decoding context** — el contexto (IHDR ya decodificado) que los decoders comparten.
- **Decoder** — clase que convierte un chunk crudo en datos con significado.
- **Registry** — mapa que relaciona cada tipo de chunk con su decoder.
- **Fachada (facade)** — capa que expone una API sencilla sobre un sistema más complejo (la fachada v1 se eliminó en la 2.0.0).
- **Parity (paridad)** — que la salida de v2 fuera idéntica a la de v1 (contrato retirado en la 2.0.0).
- **Strict mode** — modo de validación con CRC y reglas de orden.

## Roadmap

- **Fase 1** — núcleo + decoders de chunks (completada). Chunks: IHDR, tEXt, iTXt/XMP, eXIf, sRGB, bKGD.
- **Fase 2** — cobertura clásica: zTXt, iCCP, gAMA, cHRM, tRNS, tIME, pHYs, sBIT, PLTE + resolvedor de precedencia de color.
- **Fase 3** — PNG 3.0: cICP, mDCV, cLLI, APNG, `image/apng`.
- **Fase 4** — paquete Laravel (repositorio separado) que consume este núcleo.
