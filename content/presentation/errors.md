---
layout: layouts/base.njk
permalink: /presentation/errors/
title: Errors
summary: Every exception Markdown Presentation throws, and what each one means.
section: Markdown Presentation
order: 3
---

Every exception Markdown Presentation throws, in one place. The one to catch, and the advice that
is about both libraries, is on [Errors in general](/errors/).

## Presentation

| Exception | Means |
| --- | --- |
| `InvalidInput` | the base for everything a deck author can fix |
| `UnknownDirective` | a key or marker outside the closed set — the message names the set |
| `UnknownMarker` | `::click=0`, backwards, over 500, unclosed, or a stray closer |
| `UnknownLayoutRole` | `layout:` is not one of the eight |
| `UnsupportedColumn` | more `::column` markers than the layout has columns |
| `UnsupportedImageFormat` | not an image, no decoder, or over the ceiling |
| `UnsupportedLink` | `javascript:`, `data:`, `phar://`, `file://`, a UNC share |
| `ContentOverflow` | a block does not fit and `strict` is on |
| `TooManySlides` | more than 1000 |
| `MalformedFrontmatter` | not a mapping, or a value of the wrong type |
| `FencedFrontmatter` | a ```` ```yaml ```` block at the top of a slide |
| `MalformedTheme` / `ThemeNotFound` | as above |
| `UnknownStyleSlot` | `styles.yml` names a slot that does not exist |
| `MalformedTemplate` | a `template.pptx` is not one |
| `ImageNotFound` | a path, a URL, or something outside the roots |
| `UnreadablePackage` | **not** `InvalidInput`; nothing a deck names fixes it |

