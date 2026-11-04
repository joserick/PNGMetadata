---
title: ADR-005 No GD: thumbnails as bytes
lang: en-US
---

# ADR-005: No GD: thumbnails as bytes

## Status
Accepted

## Date
2026-11-02 (recorded 2026-11-03)

## Context

The first v2 iteration kept GD as an *optional* dependency: the typed core returned the
embedded EXIF thumbnail as a binary string, and the v1 facade converted it to `\GdImage` for
`getThumbnail()`, with `ext-gd` declared in composer `suggest` (supersedes the "GD optional"
stance of `modernize-architecture` D4). Once ADR-009 removed the facade, that optional
dependency had no caller left — but the question of whether the core should offer a
rendering helper was still open, and GD is not bundled in minimal PHP images.

## Decision

GD exits the package completely. The typed core is the whole story:
`MetadataDocument::thumbnailBytes()` returns the embedded thumbnail as bytes (read through
the same `ext-exif` boundary as the profile), and rendering belongs to the consumer —
`\imagecreatefromstring()` or any decoder they already use. There is no `suggest` entry, no
`GdImage` in any signature, and no runtime branch on the extension.

## Alternatives Considered

- **Keep a `ThumbnailRenderer` helper returning `\GdImage`.** Rejected: rendering is
  presentation, not extraction; the Phase-4 Laravel package is the natural owner of that
  convenience.
- **Return a `GdImage` when the extension is loaded, bytes otherwise.** Rejected: two return
  types depending on the host's php.ini is the kind of conditional API that makes consumers
  write feature detection.
- **Return the raw `eXIf` payload and let consumers dig out the thumbnail.** Rejected: the
  bytes are already inside a TIFF structure the library is prepared to read; forcing
  consumers to re-implement that is the opposite of the value the package offers.

## Consequences

- `ext-gd` appears nowhere: not in `require`, not in `suggest`, not in the dev image
  (`Dockerfile` installs `exif` only). The package installs on images where GD is absent.
- Tests assert thumbnail *bytes* rather than a resource, so the performance and unit lanes do
  not need a graphics stack.
- Consumers who want a resource pay one function call — an acceptable hand-off, documented in
  the v1→v2 migration guide.
- A future rendering helper, if it ever comes, must live outside this package.

## Sources

`remove-v1-compatibility` design D2; `modernize-architecture` design D4 (superseded);
AGENTS.md §2; `src/Metadata/MetadataDocument.php`; `composer.json`; `Dockerfile`.
