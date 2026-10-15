---
layout: layouts/base.njk
permalink: /guides/configuration-word/
title: Word configuration
summary: Every frontmatter key, every option, every style slot and its default — and the parser you have to pass to read any of it.
section: Guides
order: 0
---

## Read the frontmatter before anything else

**The default parser does not read frontmatter.** `CommonMarkParser::DEFAULT_FLAVOUR` is
GitHub-Flavored Markdown plus directives, and a document that opens with `---` and an `options:`
block has that block rendered as body text — silently, with the configuration it was meant to
carry ignored.

`withAllExtensions()` is not the answer either: it does read frontmatter, but it leaves directives
out, so `::toc` and `:footnote` stop being directives and become the text an author wrote.

So both, named:

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

The command line always has it — `mdoffice word` adds `FrontMatterExtension` on top of the default
flavour — which is why this catches people writing PHP and not people using the binary.

## Top-level keys

| Key | Type | Default | What it does |
| --- | --- | --- | --- |
| `options:` | mapping | absent | handed to `Options::fromArray()` |
| `styles:` | mapping | absent | handed to `new Styles(...)` |
| `theme:` | string | absent | a named look, or a path to one |
| `template_file:` | string | absent | a `.docx` or `.dotx` to render into |
| `fields:` | mapping | `{}` | values for the template's `MERGEFIELD`s |
| `header:` | mapping | empty | the running head |
| `footer:` | mapping | empty | the running foot |

Only `options:` and `styles:` are validated. Every other key is open-ended metadata, so a document
can carry `title:` or `author:` for a pipeline downstream that cares and be none the worse.

## Every option

| Key | Type | Default | Values |
| --- | --- | --- | --- |
| `softBreak` | string | `space` | `space`, `lineBreak`, `paragraph` |
| `hardBreak` | string | `line` | `remove`, `line`, `paragraph` |
| `html` | string | `strip` | `strip`, `preserve`, `drop` |
| `images` | string | `embed` | `embed`, `placeholder`, `skip` |
| `imageBasePath` | string | `null` | where relative paths resolve from |
| `imageMaxWidth` | number | `15.0` | centimetres, ≥ 0 |
| `svgFallback` | string | `raster` | `raster`, `none` |
| `maxHeadingLevel` | integer | `6` | clamped to 1–6 |
| `orderedListFormat` | string | `decimal` | any OOXML `w:numFmt` — not validated |
| `orderedListSuffix` | string | `tab` | `tab`, `space`, `nothing` |
| `tableBorders` | boolean | `true` | |
| `tableHeaderBold` | boolean | `true` | off makes a round trip honest about headers |
| `tableWidth` | integer | `5000` | fiftieths of a percent, 0–5000 |
| `codeBlockShading` | boolean | `true` | |
| `linkTarget` | string | `_blank` | not validated |
| `thematicBreak` | string | `border` | `border`, `text` |
| `deferredHyperlinks` | boolean | `false` | |

Booleans may be written as `true`/`false`/`yes`/`no`/`on`/`off`, in any case.

## Style slots

Each slot holds a **style id** (a string), an **inline style** (a mapping), or `null`. A mapping
merges recursively into the default; a style id or `null` replaces the slot whole.

| Slot | Default |
| --- | --- |
| `heading.1` … `heading.6` | `Heading1`…`Heading6`, bold, 16/13/12/11/11/11pt, `2F5496` |
| `paragraph` | 6pt after, 1.15 line height |
| `blockQuote` | `IntenseQuote`, italic, `404040`, 720 twips either side |
| `codeBlock` | 360 twips left, no space above or below |
| `listParagraph` | 3pt after |
| `codeFont` | Consolas 9pt `A31515` |
| `linkFont` | `0563C1`, underlined |

Font properties are `name`, `size`, `color`, `bold`, `italic`, `strikethrough`, `underline`.
Paragraph properties are `indentation`, `space`, `shading`, `keepNext`, `alignment`, `styleName`,
`lineHeight`.

A colour is **six hex digits with no `#`** and no colour name. A size is a number ≥ 0.001, and `0`
is refused with the reason: it is a size in points, and 0 makes the text invisible in Word.

## Precedence

| Source | Wins over |
| --- | --- |
| the command line | the frontmatter |
| the frontmatter | the config file |
| the config file | the defaults |

A theme is not a layer — it is folded into the defaults, and your own written slots sit on top of
it.

## Headers and footers

Both take `body`, `align` (`left`/`center`/`right`), `border` (`top`/`bottom`/`both`), and the
switches `page`, `total`, `chapter`, `filename`, `date`, `firstPage`, `even`.

A `body` may contain `{page}`, `{total}`, `{filename}`, `{chapter}` and `{date}`. With
`dateFormat` set, `{date}` becomes a Word `DATE` field with that picture rather than a fixed date.

```yaml
footer:
  align: center
  border: top
  body: "Page {page} of {total}"
  page: true
  total: true
```

## Templates

`template_file:` names a `.dotx`. `fields:` fills its merge fields, with `true` and `false`
spelled as strings because that is what Word stores. The command line adds `--require-control`,
which refuses a template with no content control instead of appending to it.
