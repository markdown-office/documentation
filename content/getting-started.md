---
layout: layouts/base.njk
permalink: /getting-started/
title: Getting started
summary: Install the suite, convert one file, change one thing. Two libraries, four lines of PHP, no Office installed.
section: Getting started
order: 0
---

Two libraries, and they do not depend on each other. Install the one you need.

```bash
composer require markdown-office/markdown-word
composer require markdown-presentation/markdown-presentation
```

Both need PHP 8.2 or newer. The Word half also needs `ext-mbstring`; both need `ext-dom`,
`ext-gd` and `ext-zip`.

## A Word document

`MarkdownToWord` takes either a path or the text itself. A string that names a file that exists is
read from it; anything else is taken as the content.

```php
use MarkdownWord\MarkdownToWord;

(new MarkdownToWord('notes.md'))->save('notes.docx');
```

That is the whole thing. Here it is running:

{% example "notes.md" %}
````md word
# Notes from the review

The library walks the Markdown **syntax tree** rather than the HTML, so what lands in the
document is the document the Markdown described.

- Headings become real headings, so Word's navigation pane works.
- Tables are tables, with a header row Word can repeat across pages.
- A list is a list, and nesting is nesting.

> Emphasis, strong emphasis, `inline code` and [links](https://commonmark.org/) all carry their
> meaning across rather than their punctuation.
````
{% endexample %}

Three output formats, all from the same converter:

```php
use MarkdownWord\Format;
use MarkdownWord\MarkdownToWord;

$converter = new MarkdownToWord('notes.md');

$converter->save('notes.docx');                    // the default
$converter->convertTo(Format::Odt, 'notes.odt');
$converter->convertTo(Format::Rtf, 'notes.rtf');
```

## A deck

The PowerPoint half is two calls: one to read the Markdown into a deck, one to write it out.
`---` separates slides, and anything before the first one is the deck's frontmatter.

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

## What a deck is allowed to say

The PowerPoint half has a closed vocabulary, and anything outside it fails the build with a
message naming what would have worked. That is deliberate: the thing writing a deck may be a
model, and a model can act on a message and cannot act on a deck that quietly came out wrong.

```php
$deck = (new DeckParser())->parse("# One\n\n---\n\n# Two\n");

foreach ($deck->slides() as $slide) {
    echo $slide->index(), ': ', $slide->title(), "\n";
}
```

```text
0: One
1: Two
```

## Back the other way

The Word half reads a document as well as writes one, and the two directions are the same
interface.

```php
use MarkdownWord\Converter;
use MarkdownWord\MarkdownToWord;
use MarkdownWord\WordToMarkdown;

function convert(Converter $converter, string $target): void
{
    $converter->save($target);
}

convert(new MarkdownToWord('notes.md'), 'notes.docx');
convert(new WordToMarkdown('report.docx'), 'report.md');
```

What a round trip does not preserve is written down in
[Round trips](/guides/round-trip/) rather than left to be discovered.

## The command line

The Word package installs a binary, and it works out which way the data has to go from the
content rather than from the name of the file.

```bash
mdword README.md          # writes README.docx
mdword README.docx        # writes README.md

mdword to-docx notes.md
mdword to-odt  notes.md
mdword to-markdown report.docx
```

```bash
cat notes.md | mdword --to docx - -o - | pbcopy
```

More in [The command line](/guides/command-line/).

## Configure it from the document

Anything the command line can do, the document can do to itself. Frontmatter at the top of the
file, with the options under `options:` and the look under `styles:`.

{% example "A themed report" %}
````md word
---
options:
  maxHeadingLevel: 2
  tableWidth: 5000
theme: corporate
---

# Quarterly report

Frontmatter configures the document that contains it, so the same file renders differently in
two pipelines without two copies of the file.

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

Every frontmatter key is listed in [Word configuration](/guides/configuration-word/). Every theme
key is in [Themes](/guides/themes/).

## On the command line instead

If you would rather not put configuration in the file, `--plain` is the fastest thing to try:
no code colouring, no quote style, no table borders, no added spacing.

```bash
mdword to-docx --plain notes.md
```

## Next

- [How directives work](/directives/) — the syntax both halves read.
- [Word directives](/directives/word/) — `::toc`, `:footnote`, and what happens to the rest.
- [Presentation directives](/directives/presentation/) — `::click`, `::column`, `::note`.
- [Errors](/reference/errors/) — every exception, and which one to catch.