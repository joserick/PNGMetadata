---
title: Introduction
lang: en-US
---

# Introduction

This section explains the fundamentals of the PNG format from scratch: what it is, how data is organized at the byte level, and what exactly a *chunk* is.

This is the foundation everything else builds on. If you have never worked with binary formats, this is the section for you.

- [What is a PNG?](/en/introduction/what-is-png.html) — historical context and why the format was designed the way it was.
- [The binary format](/en/introduction/binary-format.html) — bytes, bits, *endianness* and how to read numbers inside a file.
- [Anatomy of a chunk](/en/introduction/chunk-anatomy.html) — the fundamental unit of PNG and its hidden "flags" in the type.

## A byte's journey

Before diving into details, keep this idea in mind: when you open a PNG in a text editor, you see meaningless symbols. Those symbols are the editor's attempt to display **bytes** (numbers between 0 and 255) as characters.

A PNG file is simply **a sequence of bytes with very strict rules** about what each one means. This entire documentation is about deciphering those rules for the chunks that carry metadata.

```
Byte = 8 bits = a number between 0 and 255
      ┌───┬───┬───┬───┬───┬───┬───┬───┐
      │ 1 │ 0 │ 0 │ 1 │ 0 │ 1 │ 1 │ 0 │  = 0x96 = 150
      └───┴───┴───┴───┴───┴───┴───┴───┘
```

Continue with [What is a PNG?](/en/introduction/what-is-png.html) to get started.
