---
layout: layouts/base.njk
permalink: /
title: Markdown, into the file you actually send
summary: One Markdown document becomes a Word document or a PowerPoint deck. In PHP, with no Office and no binaries.
section: Home
order: 0
---
<div class="hero">

<div class="hero__mark">{{ wordmark | safe }}</div>

# Markdown, into the file you actually send

One document in, a finished `.docx` or `.pptx` out. Headings that are headings, tables that are
tables, a table of contents Word can build. No Office installed, no headless browser, no export
button — just a PHP library and a string.

</div>

<div class="hero__rule">The suite</div>

<ul class="cards">

<li>
<a class="card" href="/directives/word/">
<h3>Markdown Word</h3>
<p>Markdown to <code>.docx</code>, and <code>.docx</code> back to Markdown. Tables, footnotes,
headers and footers, templates, styles, and a table of contents that is a real Word field.</p>
<code>markdown-office/markdown-word</code>
</a>
</li>

<li>
<a class="card" href="/directives/presentation/">
<h3>Markdown Presentation</h3>
<p>Markdown to <code>.pptx</code>, with a Slidev-compatible splitter. Layouts, themes, columns,
click builds and speaker notes — and a refusal for anything it will not draw.</p>
<code>markdown-presentation/markdown-presentation</code>
</a>
</li>

<li>
<a class="card" href="/directives/syntax/">
<h3>CommonMark Generic Directives</h3>
<p>The syntax both halves read: <code>:name</code>, <code>::name</code>,
<code>:::name</code>. One parser extension for your own directives, if you want one.</p>
<code>markdown-office/commonmark-generic-directives</code>
</a>
</li>

</ul>

<div class="hero__rule">Start here</div>

<ul class="actions">
<li><a class="primary" href="/getting-started/">Get started</a></li>
<li><a class="ghost" href="/directives/">How directives work</a></li>
</ul>

## What it is not

Markdown is a text format and an office document is a tree of drawing instructions. There is no
mapping between them that does not involve a decision about what a thing _is_, and a library
that hides those decisions is a library that produces documents nobody meant to write.

So these libraries are loud about them.

- A deck names a directive nobody reads, the build fails and the message lists the directives
  that would have worked. It does not draw a slide with the wrong thing on it.
- An image is over the pixel ceiling, it is refused before a byte of it is decoded. A file of
  152 bytes that declares a 16000-square picture does not get to take a gigabyte to find out.
- A round trip cannot preserve everything, and the pages here say exactly what it cannot.

Every example on this site is not a screenshot of an example. It is the output of the library,
produced while this page was built — and downloadable, so you can open it in Word rather than
trust the page. If an example stops working, the build stops.

## Written to be read by a machine, too

This documentation is built from Markdown and served as static files, with three deliberate
extras for anything reading it rather than looking at it:

- **[`llms.txt`](/llms.txt)** — the whole index, one line per page.
- **[`llms-full.txt`](/llms-full.txt)** — every page in one file.
- **[`pages.json`](/pages.json)** — the index as data, with the URL of each page's Markdown.

Every page is also available as plain Markdown at its own URL, so `/getting-started/` is
`/getting-started/index.md`. Nothing here needs JavaScript, and nothing needs a network round
trip to be read.

## Requirements

PHP 8.2 or newer, and `ext-dom`, `ext-gd`, `ext-zip` — plus `ext-mbstring` for the Word half.
`ext-imagick` is optional and buys you SVG; without it an SVG is refused by name rather than
quietly rasterised badly.