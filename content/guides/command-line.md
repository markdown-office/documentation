---
layout: layouts/base.njk
permalink: /guides/command-line/
title: The command line
summary: mdoffice in full — the parts, every command, every option, and how it decides which way the data has to go.
section: Reference
order: 1
---

`mdoffice` is the whole suite at a terminal. It is a program in its own right,
[`markdown-office/mdoffice`](https://github.com/markdown-office/mdoffice), which requires both
converter packages and calls them — so what it does is what those libraries do.

```bash
mdoffice word README.md          # writes README.docx
mdoffice word README.docx        # writes README.md
mdoffice slides deck.md          # writes deck.pptx
```

## Parts

The first argument says which converter the run is for.

| Part | Does |
| --- | --- |
| `mdoffice word …` | Markdown to `.docx`, `.odt` and `.rtf`, and `.docx` back to Markdown |
| `mdoffice slides …` | Markdown to `.pptx` |

**A bare file is not a run.** `mdoffice notes.md` is refused, and the parts are listed. The
Word half decides the direction from the file's first bytes, but with more than one converter
here a name alone cannot say which way the data goes, and an error that guessed would be right
half the time and wrong the other half.

## Commands

| Part | Command | Does |
| --- | --- | --- |
| `word` | `to-docx` | Markdown to `.docx` |
| `word` | `to-odt` | Markdown to `.odt` |
| `word` | `to-rtf` | Markdown to `.rtf` |
| `word` | `to-markdown` | a Word document to Markdown |
| `slides` | `to-pptx` | Markdown to `.pptx` |

In the Word part, with no command, the direction is detected. `--to
<docx|word|odt|rtf|markdown|md>` overrides it. The slides part has one command, so
`mdoffice slides deck.md` is the same run as `mdoffice slides to-pptx deck.md`.

## Options for `to-docx`, `to-odt` and `to-rtf`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input; `-` for standard output |
| `-c, --config <file.php>` | path | none |
| `--theme <name>` | name or path | the frontmatter's `theme:` |
| `-t, --template <file>` | `.docx` or `.dotx` | none — `.docx` only |
| `--require-control` | flag | off |
| `--images <mode>` | `embed`, `placeholder`, `skip` | `embed` |
| `--no-images` | flag | shorthand for `--images skip` |
| `--image-base <dir>` | path | the input's directory |
| `--svg-fallback <mode>` | `raster`, `none` | `raster` |
| `--table-width <n>` | 0–5000 | `5000` |
| `--plain` | flag | off |
| `--to <format>` | see above | detected |

`--plain` is the fastest thing to try: no code colouring, no quote style, no table borders, no
added spacing.

Only `to-docx` takes `--template`. The others answer `A template cannot be filled in for <format>.`

A document that names its own template does so with `template_file:` in its frontmatter, which
is read whichever way the document is converted.

## Options for `to-markdown`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input, `.md` |
| `--media <dir>` | path | no extraction |
| `--line-ending <lf\|crlf>` | `lf`, `crlf` | `lf` |
| `--setext` | flag | off |
| `--no-fence` | flag | off |
| `--no-header` | flag | off |
| `--to <format>` | | detected |

## Options for `to-pptx`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input; `-` for standard output |
| `--strict` | flag | off — a deck that does not fit is shrunk to fit |
| `--image-base <path>` | path | may be repeated |

A deck is read with the Slidev-compatible dialect, so a `---` line closes the slide being read
and `---` on its own opens the frontmatter block.

There is no `--theme`: the renderer resolves the theme from the deck's own `theme:`.

## Exit codes

`0` on success, `1` for a bad input or a configuration problem. An error is printed as the
sentence alone — no class name, no line from any repository — because it is the caller's mistake
to fix and not a defect in the libraries.

## Things it is easy to get wrong

- `--output=x`, `--output x` and `-o x` are the same. Leading dashes are stripped before the
  lookup, so even `-o=x` works.
- Everything after a bare `--` is an operand, and a lone `-` is standard input.
- Using another direction's option says which command it belongs to:

  ```text
  The option "--media" does not apply here.
  It belongs to `to-markdown`.
  ```

## Pipes

```bash
cat notes.md | mdoffice word to-docx - -o - | pbcopy
cat deck.md  | mdoffice slides to-pptx - -o - | pbcopy
```

Reading standard input and writing standard output both work, which makes it a filter rather than
a command that has to be handed a filename. The result leaves through standard output and
everything else goes to standard error, so a pipe gets a document and not a progress line.

## What is lost

A run that dropped something says so on standard error, naming the feature:

```text
mdoffice word: odt cannot carry table-borders: a table comes out with no borders
```

The Word half reports what a target format cannot carry. The slides half reports what it had to
shrink to make fit — a block that is clamped rather than drawn over whatever comes next, so the
file itself says nothing about it.

## Getting it

```bash
brew install markdown-office/tap/mdoffice      # macOS and Linux
winget install markdown-office.mdoffice        # Windows
```

Or download a single self-contained executable for your platform from
[the releases page](https://github.com/markdown-office/mdoffice/releases) — it carries its own
PHP and needs nothing installed.

For a machine that would rather use its own PHP, `mdoffice.phar` is attached to every release as
well:

```bash
curl -L -o mdoffice.phar \
  https://github.com/markdown-office/mdoffice/releases/latest/download/mdoffice.phar
chmod +x mdoffice.phar
./mdoffice.phar --version
```

## `--version`, and which library produced the document

```bash
$ mdoffice --version
mdoffice 0.1.0
  markdown-office/markdown-word            0.2.0
  markdown-office/markdown-presentation    0.1.0
```

The tool versions itself separately from the libraries it holds, because it is neither of them: a
CLI-only change is a reason to release, and one version across three repositories would mean a
Word-only fix forcing a presentation release. The libraries are read from the install rather
than declared, so a `--version` and the files beside it cannot disagree.

`mdoffice word --version` reports the Word half alone.