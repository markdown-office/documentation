---
layout: layouts/base.njk
permalink: /getting-started/
title: Getting started
summary: Convert one file. Then add look and feel. Then add special features. Then use the command line.
section: Getting started
order: 0
---

Four steps, each one small enough to be its own page of this site. The first works on its own; the
rest are what you add when the first is not enough.

```bash
composer require markdown-office/markdown-word
composer require markdown-presentation/markdown-presentation
```

PHP 8.2 or newer. The Word half also needs `ext-mbstring`; both need `ext-dom`, `ext-gd` and
`ext-zip`.

---

## 1. Convert a file

Two libraries, four lines of PHP, no Office.

```php
use MarkdownWord\MarkdownToWord;

(new MarkdownToWord('notes.md'))->save('notes.docx');
```

`MarkdownToWord` takes either a path or the text. A string naming a file that exists is read from
it; anything else is taken as the content.

Three output formats from the same converter:

```php
use MarkdownWord\Format;
use MarkdownWord\MarkdownToWord;

$converter = new MarkdownToWord('notes.md');

$converter->save('notes.docx');                    // the default
$converter->convertTo(Format::Odt, 'notes.odt');
$converter->convertTo(Format::Rtf, 'notes.rtf');
```

Here it is running. Headings are headings, so Word's navigation pane works; the table is a table,
with a header row Word can repeat across a page.

{% example "notes.md" %}
````md word
# Notes from the review

The library walks the Markdown **syntax tree** rather than the HTML, so what lands in the
document is the document the Markdown described.

- Headings become real headings.
- Tables are tables.
- A list is a list, and nesting is nesting.

> Emphasis, strong emphasis, `inline code` and [links](https://commonmark.org/) all carry their
> meaning across rather than their punctuation.
````
{% endexample %}

And a deck is two calls. `---` separates slides, and anything before the first one is the deck's
frontmatter.

```php
use MarkdownPresentation\Document\DeckParser;
use MarkdownPresentation\Render\PresentationRenderer;

$deck = (new DeckParser())->parse($markdown);

(new PresentationRenderer())->render($deck, 'deck.pptx');
```

{% example "A deck with a title slide and a content slide" %}
````md presentation
---
theme: default
author: Markdown Office
---

# Markdown Office

Convert a Markdown document into a deck that opens in PowerPoint.

---

## Why a slide and not a page

- One `---`, one slide
- A theme decides the type, the colour and the grid
- `::click` builds the slide up on a click

::note
Say the name of the library once, then move on. The slide is the argument.
::
````
{% endexample %}

## And back again, for Word

The Word half reads a document as well as writes one, and the two directions are the same
interface — so a round trip is two calls, and nothing else in the suite has a second one.

```php
use MarkdownWord\MarkdownToWord;
use MarkdownWord\WordToMarkdown;

(new WordToWord())->save('notes.docx');
(new WordToMarkdown('notes.docx'))->save('notes.md');
```

For standard Markdown this is close to lossless. What it cannot preserve is on
[Round trips](/guides/round-trip/), and that page is worth reading before you rely on it.

---

## 2. Add look and feel

A document configures itself. Frontmatter at the top of the file, with the options under
`options:` and the look under `styles:` or `theme:`.

**One thing first.** The default `markdown-word` parser does **not** read frontmatter — an
`options:` block is rendered as body text and the configuration is ignored. The command line always
has it; in PHP you name the extensions:

```php
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;
use MarkdownWord\MarkdownToWord;
use MarkdownWord\Parser\CommonMarkParser;

$parser = new CommonMarkParser([
    GithubFlavoredMarkdownExtension::class,
    GenericDirectiveExtension::class,
    FrontMatterExtension::class,
]);

(new MarkdownToWord($markdown, parser: $parser))->save('out.docx');
```

`withAllExtensions()` is not the answer either — it reads frontmatter and drops directives. The
whole of it is on [Word configuration](/guides/configuration-word/).

{% example "A themed report" %}
````md word
---
options:
  maxHeadingLevel: 2
  tableWidth: 5000
theme: corporate
---

# Quarterly report

The same file renders differently in two pipelines without two copies of the file.

## Highlights

| Region  | Revenue | Change |
| ------- | ------: | -----: |
| North   |   1,204 |  +8.2% |
| South   |     988 |  -1.1% |
| East    |   1,533 | +12.4% |

## Notes

The `theme:` key names a look that ships with the library. Everything under `options:` and
`styles:` is yours and overrides it.
````
{% endexample %}

A deck is configured the same way, with a closed set of seven keys:

| Key | | |
| --- | --- | --- |
| `title` | string | the slide's title |
| `name` | string | a fallback for `title` |
| `level` | integer | the heading depth a title is drawn at |
| `layout` | one of eight roles | which layout to use |
| `theme` | string | **first slide only** |
| `author` | string | **first slide only** |
| `description` | string | **first slide only** |

{% example "Every frontmatter key on a deck" %}
````md presentation
---
theme: corporate-dark
title: The quarter in three slides
author: Markdown Office
description: What changed, what it cost, and what happens next.
---

# The quarter

## What changed

::click

- Two products shipped

::click

- Three customers renewed

::note
Lead with the renewal number. It is the one people remember.
::
````
{% endexample %}

Themes, and the whole geometry they control, are on [Themes](/guides/themes/).

---

## 3. Add special features

Configuration decides how something looks. **Directives** are for things Markdown has no
vocabulary for — a table of contents, a footnote, a click build, a speaker note.

One syntax, three shapes, and the number of colons is the whole difference:

| Shape | Written | For |
| --- | --- | --- |
| Text | `:name[label]{attrs}` | inside a paragraph |
| Leaf | `::name[label]{attrs}` | one line |
| Container | `:::name{attrs}` … `:::` | a fenced block |

**Word draws two.** A table of contents, and a footnote.

{% example "A table of contents Word can build" %}
````md word
# Quarterly report

::toc{depth=2, title="Contents"}

## Highlights

The figure was provisional and has since been confirmed.

## Method

Word will build the table on open. If it does not, select it and press F9.
````
{% endexample %}

The field goes out empty and marked dirty, with `w:updateFields` set so Word builds it on open —
because PHPWord's cached page numbers are all `1`, and a reader who never updates a field shows
them as they are.

**PowerPoint draws four.** A click build, a step region, a column, and a speaker note:

{% example "A slide that builds up, with a note" %}
````md presentation
## Three things

- One
- Two

::click

- Two is the interesting one
- Three

::click

- All three

::note
Say "two" slowly. That is the point of the slide.
::
````
{% endexample %}

Beyond those, the presentation half has a **closed set**: anything outside it fails the build with
a message naming what would have worked. The Word half is the opposite — it writes an unrecognised
name into the document as the text the author typed. Both are deliberate, and
[How directives work](/directives/) says why.

---

## 4. Use the command line

The Word package installs a binary, and it works out which way the data has to go from the content
rather than from the name of the file. A Word document is a zip and Markdown is text, and the four
bytes that say which is which are part of the format.

```bash
mdword README.md          # writes README.docx
mdword README.docx        # writes README.md
```

Explicit, when you want to be:

```bash
mdword to-docx notes.md
mdword to-odt  notes.md
mdword to-rtf  notes.md
mdword to-markdown report.docx
```

A filter, not a command that needs a filename:

```bash
cat notes.md | mdword --to docx - -o - | pbcopy
```

`--plain` is the fastest thing to try — no code colouring, no quote style, no table borders, no
added spacing — and `--theme corporate` is the one that changes the look:

```bash
mdword to-docx --theme corporate --plain notes.md
```

There is no command-line tool for the presentation half yet. Every option, and a few traps — the
built-in usage text names two options that do not exist — is on
[The command line](/guides/command-line/).

---

## Where to next

- [How directives work](/directives/) — the syntax both halves read.
- [Word directives](/directives/word/) · [Presentation directives](/directives/presentation/)
- [Themes and layout](/guides/themes/) · [Images](/guides/images/) · [Round trips](/guides/round-trip/)
- [Errors](/reference/errors/) — every exception, and the one to catch.
- [The suite](/suite/) — the three packages, and the spreadsheet that is coming.