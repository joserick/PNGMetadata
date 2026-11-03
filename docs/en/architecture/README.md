---
title: Project architecture
lang: en-US
---

# Project architecture

This section explains how the PNGMetadata code is organized and why it was designed that way.

- [Layered architecture](/en/architecture/architecture.html) — the two layers and the dependency rule.
- [Public API](/en/architecture/api.html) — the typed API of the library.

## The idea in one sentence

> **One responsibility per layer**: read bytes → turn them into meaningful data → expose them conveniently.

The code never mixes these three concerns. This makes adding a new chunk type trivial (as you will see on the chunks page).
