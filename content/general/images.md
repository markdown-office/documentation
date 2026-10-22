---
layout: layouts/base.njk
permalink: /images/
title: Images
summary: What is carried, what is converted, what is refused, and the pixel ceiling that makes a 152-byte file expensive.
section: In general
order: 2
---

## The decision

| | |
| --- | --- |
| **Carried** as-is | GIF, JPEG, PNG |
| **Converted** to PNG | anything GD can decode — WebP, BMP |
| **Rasterised** | SVG, when `ext-imagick` can draw it |
| **Refused** | anything else, anything unreadable, anything too large |

A format nothing here can decode is **refused by name**, with a message naming the format. It is
never drawn as a placeholder, because a deck with a picture missing is the one outcome this suite
refuses to produce anywhere.

Alpha survives a conversion: the alpha channel is kept and the result is written as PNG.

## The ceiling

**16,777,216 pixels** — 4096 by 4096 — on both halves, enforced on the *header*.

This is worth a moment, because the obvious implementation of it is wrong. Measuring a raster
means decoding it, and decoding a picture is what allocates the memory you were trying to bound.
For a vector it is worse: a file of **152 bytes** declaring a 16000-square drawing is a file that
wants a 1.9 GB canvas before anything has decided whether it may have one.

So both halves read the size without drawing:

```text
152-byte SVG  ->  refused in 0.05s, 4 MB peak
```

which is what `pingImage()` is for. It reads the header and stops, and the header is worth
trusting because the delegate sizes the canvas from the same header: a file declaring
`width="10"` beside a `viewBox` of 9000 is drawn at 10, not at 9000.

**There is no second line of defence**, and it is worth saying why rather than shipping one.
ImageMagick's own resource limits do not bound an SVG render: measured against a 4000-square
file, `-limit area 1000 -limit memory 4MiB -limit map 4MiB` on the command line produced the
picture anyway. An area limit would have read as protection and been nothing.

## SVG

Word and PowerPoint both hold a vector only *beside* a raster of it, so something has to draw the
raster. GD has no entry point for a vector in any PHP version, so `ext-imagick` is the only
rasteriser here — and it is in `suggest`, not `require`.

```json
"ext-imagick": "Embeds SVG. PowerPoint holds a vector only beside a raster of it, so
something has to draw the raster; without this an SVG raises rather than going in as a picture
no reader can show."
```

Without it, an SVG is refused by name rather than rasterised badly.

**A build is not just "is the extension loaded".** `SvgRasteriser::available()` renders a probe
and checks for ink. A build of ImageMagick with no fonts configured answers `--version` perfectly
and draws every `<text>` as nothing; that is a real configuration found on a real machine, and the
probe is what catches it.

### What SVG costs you

The part in the package is a **PNG**, not the vector. What appears in the deck is what this
rasteriser drew, not what the file described — and:

- A `<text>` is in the picture only as far as the delegate draws a letter.
- **An SVG carrying its own font as a data-URI `@font-face` is silently substituted.** The render
  comes out identical to the "that family does not exist" fallback. Nothing detects this; it is
  documented here because it costs an afternoon otherwise.

