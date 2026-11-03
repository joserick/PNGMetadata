---
title: Arquitectura del proyecto
lang: es-ES
---

# Arquitectura del proyecto

Esta sección explica cómo está organizado el código de PNGMetadata y por qué se diseñó así.

- [Arquitectura en capas](/es/architecture/architecture.html) — las dos capas y la regla de dependencia.
- [API pública](/es/architecture/api.html) — la API tipada de la librería.

## La idea en una frase

> **Una sola responsabilidad por capa**: leer bytes → convertirlos en datos con sentido → exponerlos de forma cómoda.

El código nunca mezcla estas tres preocupaciones. Esto hace que añadir un nuevo tipo de chunk sea trivial (verás cómo en la página de chunks).
