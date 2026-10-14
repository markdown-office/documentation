---
layout: layouts/base.njk
permalink: /guides/command-line/
title: The command line
summary: mdword in full — every command, every option, and how it decides which way the data has to go.
section: Reference
order: 1
---

`mdword` is the whole library at a terminal. It ships with the package and Composer puts it at
`vendor/bin/mdword`.

```bash
mdword README.md          # writes README.docx
mdword README.docx        # writes README.md
```

**It decides from the content, not the name.** A Word document is a zip archive and Markdown is
text, and the four bytes that say which is which are part of the format rather than a convention —
so a Markdown file called `notes.docx` still converts the right way.

## Commands

| Command | Does |
| --- | --- |
| `to-docx` | Markdown to `.docx` |
| `to-odt` | Markdown to `.odt` |
| `to-rtf` | Markdown to `.rtf` |
| `to-markdown` | a Word document to Markdown |

With no command, the direction is detected. `--to <docx\|word\|odt\|rtf\|markdown\|md>` overrides
it.

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

## Exit codes

`0` on success, `1` for a bad input or a configuration problem. An error is printed as the
sentence alone — no class name, no line from this repository — because it is the caller's mistake
to fix and not a defect in the library.

## Things it is easy to get wrong

- `--output=x`, `--output x` and `-o x` are the same. Leading dashes are stripped before the
  lookup, so even `-o=x` works.
- Everything after a bare `--` is an operand, and a lone `-` is standard input.
- Using another direction's option says which command it belongs to:

  ```text
  The option "--media" does not apply here.
  It belongs to `to-markdown`.
  ```

- **The built-in usage text is wrong.** `mdword --help` shows an example using `--region body
  --define customer=Northwind`, and **neither is an option of any command**. Both are rejected at
  runtime. Template values come from the document's `fields:` instead.

## Pipes

```bash
cat notes.md | mdword --to docx - -o - | pbcopy
```

Reading standard input and writing standard output both work, which makes it a filter rather than
a command that has to be handed a filename.

## A phar

```bash
curl -L -o mdword.phar https://github.com/markdown-office/markdown-word/releases/latest/download/mdword.phar
chmod +x mdword.phar
./mdword.phar --version
```

`symfony/yaml` is bundled into it, so the phar is self-contained.
