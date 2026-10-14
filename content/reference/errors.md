---
layout: layouts/base.njk
permalink: /reference/errors/
title: Errors
summary: Every exception in the suite, and the one to catch for each package.
section: Reference
order: 0
---

An exception is only useful if it says what to do next. Every message in this suite names the
thing that would have worked instead, and the pages below are those messages in one place.

## The one to catch

**`MarkdownWord\Exception\InvalidConfiguration`** is the one type to catch for the Word half.
`UnknownConfigurationKey` covers the key half — a key nobody reads — and
`InvalidConfigurationValue` covers the rest. Both extend it, and both extend `Exception\InvalidInput`.

```php
use MarkdownWord\Exception\Exception as WordException;
use MarkdownWord\Exception\InvalidConfiguration;

try {
    (new MarkdownToWord($markdown))->save('out.docx');
} catch (InvalidConfiguration $error) {
    // Every problem at once, with line numbers and a "Did you mean".
    fwrite(STDERR, $error->getMessage());
}
```

**`MarkdownPresentation\Exception\Exception`** covers everything the presentation half reports,
so one `catch` does not also catch the failures of the libraries underneath. The failures a deck
author can fix by naming something else extend `Exception\InvalidInput` below it.

One sits beside those two rather than under `InvalidInput`: `Exception\UnreadablePackage`. Nothing
a deck names fixes a package that is not a package.

## Word

| Exception | Means |
| --- | --- |
| `InvalidConfiguration` | the key half; the base for the next one |
| `UnknownConfigurationKey` | a key nothing reads |
| `InvalidConfigurationValue` | a key whose value is not what it is used as |
| `InvalidInput` | the base for input-shaped failures |
| `NothingToConvert` | no Markdown was given |
| `UnreadableFile` | the input is not a file |
| `UnreadableDocument` | the bytes are not a Word document |
| `MalformedDocument` | it is one, and it is broken |
| `FileNotWritable` | the target cannot be written |
| `TemplateNotFound` | `template_file:` names nothing |
| `NoContentControl` | `--require-control`, and the template has none |
| `MalformedTheme` | a theme file is not shaped like one |
| `ThemeNotFound` | a theme name resolves to nothing — the message lists the ones that do |
| `UnknownStyleSlot` | `styles:` names a slot that does not exist |
| `UnsupportedElement` | something in the document cannot be drawn |
| `UnsupportedImageFormat` | see [Images](/guides/images/) |

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

## The two `UnknownDirective`

Two unrelated classes share the name. Importing the wrong one is a confusing afternoon:

| Class | Thrown when |
| --- | --- |
| `MarkdownOffice\CommonMark\Directive\Exception\UnknownDirective` | a name is not in a `DirectiveRegistry`, during parsing |
| `MarkdownPresentation\Exception\UnknownDirective` | a Slidev directive or marker is not drawn, during rendering |

Both extend `RuntimeException`; only the second extends `InvalidInput`.

## Messages repeat absolute paths

This is deliberate and there is no switch for it:

```text
Unable to read "ppt/presentation.xml" out of "/Users/you/deck.pptx".
```

The alternative is a message a machine cannot act on. A switch would be something somebody would
eventually get wrong — by turning it on in the place where the paths are harmless and off where
they are the only way to find the file.

## When something is lost rather than refused

Not everything is an exception. A feature a format cannot carry is reported rather than thrown:

```php
$converter = (new MarkdownToWord('notes.md'));

$converter->save('notes.docx');

foreach ($converter->pendingLosses() as $loss) {
    echo $loss->feature(), ': ', $loss->explain(), "\n";
}
```

`.docx` carries everything, so `pendingLosses()` is empty. `.odt` and `.rtf` drop named features —
every list item left out of an RTF, an SVG flattened to a raster, a `::toc` left as its text — and
the command line prints them to standard error rather than letting the document quietly lose them.
