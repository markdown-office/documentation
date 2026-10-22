---
layout: layouts/base.njk
permalink: /command-line/presentations/
title: For presentations
summary: The options `mdoffice slides to-pptx` takes, and the dialect the deck is read with.
section: The command line
order: 2
---

The slides part has one command, so `mdoffice slides deck.md` is the same run as `mdoffice slides
to-pptx deck.md`. The options it takes are here; the options the Word part takes are on [For
word](/command-line/word/).

## Options for `to-pptx`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input; `-` for standard output |
| `--strict` | flag | off — a deck that does not fit is shrunk to fit |
| `--image-base <path>` | path; may be repeated | the deck's own directory |
| `-h, --help` | flag | off |

A deck is read with the Slidev-compatible dialect, so a `---` line closes the slide being read
and `---` on its own opens the frontmatter block.

There is no `--theme`: the renderer resolves the theme from the deck's own `theme:`.

