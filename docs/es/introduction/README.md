---
title: Introducción
lang: es-ES
---

# Introducción

Esta sección explica los fundamentos del formato PNG desde cero: qué es, cómo se organizan los datos a nivel de bytes, y qué es exactamente un *chunk*.

Esta es la base sobre la que se construye todo lo demás. Si nunca has trabajado con formatos binarios, esta es la sección para ti.

- [¿Qué es un PNG?](/es/introduction/what-is-png.html) — contexto histórico y por qué el formato se diseñó como se diseñó.
- [El formato binario](/es/introduction/binary-format.html) — bytes, bits, *endianness* y cómo leer números dentro de un archivo.
- [Anatomía de un chunk](/es/introduction/chunk-anatomy.html) — la unidad fundamental del PNG y sus "banderas" ocultas en el tipo.

## El recorrido de un byte

Antes de entrar en detalles, ten en mente esta idea: cuando abres un PNG en un editor de texto, ves símbolos sin sentido. Esos símbolos son el intento del editor de mostrar **bytes** (números entre 0 y 255) como caracteres.

Un archivo PNG es simplemente **una secuencia de bytes con reglas muy estrictas** sobre qué significa cada uno. Toda esta documentación trata de descifrar esas reglas para los chunks que contienen metadatos.

```
Byte = 8 bits = un número entre 0 y 255
      ┌───┬───┬───┬───┬───┬───┬───┬───┐
      │ 1 │ 0 │ 0 │ 1 │ 0 │ 1 │ 1 │ 0 │  = 0x96 = 150
      └───┴───┴───┴───┴───┴───┴───┴───┘
```

Sigue con [¿Qué es un PNG?](/es/introduction/what-is-png.html) para empezar.
