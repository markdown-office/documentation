---
layout: layouts/base.njk
permalink: /word/images/
title: Image options
summary: The three options that decide what happens to a picture, and the two calls that report what happened to it.
section: Markdown Word
order: 4
---

Word holds a picture one of three ways, and the option says which. The formats themselves — what
is carried, what is converted, what is refused, and the ceiling on a raster — are the same as
the deck's, and are on [Images](/images/).

## The options

| Option | Default | |
| --- | --- | --- |
| `images` | `embed` | `embed`, `placeholder`, `skip` |
| `imageMaxWidth` | `15.0` | centimetres |
| `svgFallback` | `raster` | `none` for readers that can take the vector |

`pendingImageConversions()` reports what was converted, and `pendingLosses()` says when the vector
was written with no raster beside it — which means a reader that does not prefer the vector draws
nothing at all where an SVG was.
