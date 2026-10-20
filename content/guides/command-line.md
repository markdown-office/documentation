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

**A bare file is not a run.** `mdoffice notes.md` is refused, and the parts are listed:

```text
mdoffice: Unknown part "notes.md".

  mdoffice <part> — one of:

  word       Markdown to .docx, .odt and .rtf, and .docx back to Markdown
  slides     Markdown to .pptx
```

The Word half decides the direction from the file's first bytes, but with more than one converter
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

In the Word part, with no command, the direction is detected. `--to` overrides it: `docx` or
`word` for a Word document, `odt` for an OpenDocument Text, `rtf` for Rich Text Format, and
`markdown` or `md` for Markdown. `opendocument` and `rich text` say the same as `odt` and `rtf`.
The slides part has one command, so `mdoffice slides deck.md` is the same run as `mdoffice slides
to-pptx deck.md`.

A command named on the command line decides the direction itself, and `--to` is then only allowed
to agree with it:

```text
mdoffice word: --to docx does not match what this reads; it takes odt.
mdoffice word:   Omit it and let mdoffice word work the direction out from the file.
```

The two forms do not take quite the same options. With no command named, the direction is worked
out from a list of its own, and that list does not carry `--theme` or `--require-control`:

```text
mdoffice word: Unknown option "--theme".
mdoffice word:   Run `mdoffice help` to see what each command accepts.
```

Naming the command takes them, so `mdoffice word to-docx notes.md --theme corporate` is the way
to write it.

Every command answers `--help` on its own, and every part answers `help`:

```bash
mdoffice help
mdoffice word help
mdoffice word to-docx --help
mdoffice slides to-pptx --help
```

## Options for `to-docx`, `to-odt` and `to-rtf`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input; `-` for standard output |
| `-c, --config <file.php>` | path | none |
| `--theme <name>` | name or path | the frontmatter's `theme:` |
| `-t, --template <file>` | `.docx` or `.dotx` | none |
| `--require-control` | flag | off |
| `--images <mode>` | `embed`, `placeholder`, `skip` | `embed` |
| `--no-images` | flag | shorthand for `--images skip` |
| `--image-base <dir>` | path | the input's directory; the working directory for standard input |
| `--svg-fallback <mode>` | `raster`, `none` | `raster` |
| `--table-width <n>` | fiftieths of a percent; 0–5000 | `5000`, the full width; `0` leaves the width to Word |
| `--plain` | flag | off |
| `--to <format>` | `docx`, `word` for `to-docx`; `odt` for `to-odt`; `rtf` for `to-rtf` | the command names the direction |
| `-h, --help` | flag | off |

`--plain` is the fastest thing to try: no code colouring, no quote style, no table borders, no
added spacing.

Only `to-docx` takes `--template`. The others answer:

```text
mdoffice word: A template cannot be filled in for odt.
mdoffice word:   A template is a .docx or .dotx, and only `mdoffice word to-docx` renders into one.
```

A document that names its own template does so with `template_file:` in its frontmatter, and that
is read whichever of the three formats the document is converted to — so a document with a
`template_file:` is refused for `.odt` and `.rtf` in the same words. `--template` outranks the
frontmatter.

## Options for `to-markdown`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input, `.md` |
| `--media <dir>` | path | no extraction |
| `--line-ending <lf\|crlf>` | `lf`, `crlf` | `lf` |
| `--setext` | flag | off — headings are written as `# Heading` |
| `--no-fence` | flag | off — a monospaced paragraph comes back as a fenced code block |
| `--no-header` | flag | off — the first row of a table is a header row |
| `--to <format>` | `markdown`, `md` | the command names the direction |
| `-h, --help` | flag | off |

A line ending may also be written `unix`, `n`, `dos`, `windows` or `r`.

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

## Exit codes

`0` on success, and `1` for anything that did not finish — whether the cause is a mistake the
caller can put right or a defect they cannot. A mistake is printed as the sentence alone,
because it is the caller's to fix:

```text
mdoffice word: The file "nosuch.md" does not exist.
mdoffice word:   Name the file to read, or omit it to read standard input.
```

A defect is printed with its class and the line it happened on, because it is nobody's to fix and
somebody's to see: `mdoffice word: <ExceptionClass>: <message>`, and then the file and the line
underneath it.

## Things it is easy to get wrong

- `--output=x`, `--output x` and `-o x` are the same. Leading dashes are stripped before the
  lookup, so even `-o=x` works.
- Everything after a bare `--` is an operand, and a lone `-` is standard input.
- An option no command takes is named, and the hint says which command it does belong to:

  ```text
  mdoffice word: The option "--media" does not apply here.
  mdoffice word:   It belongs to `to-markdown`.
  ```

- An option no command takes at all is refused with the way out:

  ```text
  mdoffice word: Unknown option "--nope".
  mdoffice word:   Run `mdoffice help` to see what each command accepts.
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

There is no release to download yet: the repository has no tag, the releases page is empty, and
there is no Homebrew or winget package. The tool is run from a checkout, and the checkout needs
its two siblings beside it, because the manifest resolves both libraries through `path`
repositories pointing at `../markdown-word` and `../markdown-presentation`.

```bash
git clone https://github.com/markdown-office/mdoffice.git
git clone https://github.com/markdown-office/markdown-word.git
git clone https://github.com/markdown-office/markdown-presentation.git
cd mdoffice && composer install

php bin/mdoffice word notes.md
```

A PHAR is one command away, and it carries the two libraries inside it. The build rewrites the
two `path` repositories as `vcs` and resolves the libraries from GitHub, so the archive is one
file that needs nothing beside it:

```bash
composer build:phar          # build/mdoffice.phar
php build/mdoffice.phar word notes.md
```

## `--version`, and which library produced the document

An installed copy answers with its own version and the two libraries it holds:

```bash
$ mdoffice --version
mdoffice 0.1.0
  markdown-office/markdown-word            0.2.0
  markdown-office/markdown-presentation    0.1.0
```

The tool versions itself separately from the libraries it holds, because it is neither of them: a
CLI-only change is a reason to release, and one version across three repositories would mean a
Word-only fix forcing a presentation release. The libraries are read from the install rather
than declared, so a `--version` and the files beside it cannot disagree — and a checkout
answers with the branch it resolved rather than with a release, because that is what it holds.

`mdoffice word --version` reports the Word half alone, on one line:

```bash
$ mdoffice word --version
mdoffice word 0.2.0
```
