---
title: ADR-010 Carreras de pruebas y gate de coverage
lang: es-ES
---

# ADR-010: Carreras de pruebas y gate de coverage

## Status
Accepted

## Date
2026-11-03 (registrado 2026-11-03)

## Context

La suite pasaba en verde sin ningún driver de coverage ("No code coverage driver available"),
así que dos comportamientos especificados vivían solo en el papel — la garantía de memoria del
IDAT lazy de 50 MB y los flags de propiedad de `ChunkType` — y el contenedor de desarrollo que
producía los números era conocimiento tribal ("rebuild — see project maintainer"). Los fixtures
de terceros además están prohibidos por las reglas del repo (licencias y datos personales), así
que tampoco se podía commitear evidencia del mundo real.

## Decision

Dividir el trabajo en tres carreras y convertir el coverage en un número medido y con gate:

- **unit** (`composer test`): la suite por defecto, excluyendo el grupo `performance` vía
  `phpunit.xml`. Rápida, determinista, sin stack gráfico.
- **performance** (`composer test:performance`): `#[Group('performance')]`, corriendo con
  `php -d memory_limit=512M` porque el fixture de 50 MB solo ya supera el default de 128 MB.
- **integration** (`composer test:integration`): `tests/Integration`, dirigido por
  `PNGSUITE_DIR`; salta ruidosamente si no está definido y nunca descarga el corpus en CI.
- **Coverage**: `composer coverage` (clover + texto) alimenta `tools/check-coverage.php`, un
  script propio de ~50 líneas que compara la tasa de statements del clover contra un
  `BASELINE = 89.0` commiteado y sale con código distinto de cero por debajo. El baseline es un
  ratchet: solo puede subir.
- **Ambiente**: la imagen `pngmeta-dev` se construye desde el `Dockerfile` commiteado
  (`php:8.3-cli` + `exif` + pcov; GD ausente por diseño), así que cualquier máquina puede
  reproducir el número. CI conserva la matriz 8.3/8.4/8.5 con `coverage: none` y añade un job
  `coverage` (8.3 + pcov) que mide, aplica el gate, corre la lane de performance y sube
  `coverage.xml`.

## Alternatives Considered

- **Una dev dependency de terceros para el umbral de coverage.** Descartado: la comprobación
  son ~50 líneas y el paquete sigue sin dependencias en `tools/`.
- **Configuración `<coverage>` siempre activa en `phpunit.xml`.** Descartado en la práctica: su
  presencia hace que PHPUnit recopile coverage en *cada* ejecución, así que `composer test`
  requeriría pcov. La configuración vive en `<source>` más las banderas del script
  `composer coverage`.
- **Un servicio hosted de coverage (Codecov, etc.).** Descartado como fuera de alcance: el
  artefacto más el gate son el mecanismo; un dashboard no cambia nada de la ejecución.
- **Que CI descargue PngSuite.** Descartado: el flakiness de red envenenaría el gate; el corpus
  corre a demanda en local o antes de release.
- **Expectativas golden por archivo para el corpus.** Descartado: afirmaciones corpus-agnostic
  (documento tipado o excepción tipada, `toArray()` es array) mantienen el coste de
  mantenimiento en cero; el mapeo golden es un change aparte y más grande.
- **xdebug en lugar de pcov.** Descartado: pcov está hecho a medida para coverage y es mucho
  más liviano.

## Consequences

- El coverage no puede retroceder en silencio; cada grupo de esta suite subió el piso (84 → 89)
  añadiendo atribución `#[CoversClass]`, que es también la razón por la que la convención de
  atribución está escrita en AGENTS.md §8.
- `composer test` sigue siendo el inner loop rápido, y el trabajo pesado o dependiente del
  ambiente es explícito — pero nada de esto es opcional al publicar: AGENTS.md §6 lista la
  corrida completa de lanes como checklist pre-release.
- El comportamiento acotado en memoria ahora es una afirmación testeada y no una frase de spec.
- La cobertura aleatoria (`tests/Unit/RandomizedCorpusTest.php`) usa seeds fijos a través de
  `\Random\Randomizer`, así que es determinista por versión de PHP y segura entre re-ejecuciones.
- El gate mide solo líneas atribuidas: una clase de test sin `#[CoversClass]` no aporta nada,
  lo que subestima la medición hasta que se nota — de ahí la convención explícita.

## Sources

`testing-hardening` design D1–D8; AGENTS.md §6 y §8; `Dockerfile`; `phpunit.xml`;
scripts de `composer.json`; `tools/check-coverage.php`; `.github/workflows/main.yml`.
