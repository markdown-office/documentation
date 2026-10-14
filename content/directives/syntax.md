{% raw %}


This page is the syntax itself, without either library in it. It is implemented by
`markdown-office/commonmark-generic-directives` and it is what both halves of the suite parse.

## Three shapes, and the colons are the difference

| Shape | Written | Applies | Contains |
| --- | --- | --- | --- |
| Text | `:name[label]{attrs}` | inline | nothing |
| Leaf | `::name[label]{attrs}` | block, one line | nothing |
| Container | `:::name{attrs}` … `:::` | block | any Markdown |

A leaf has **no body**. It is one line, and the line after it is not part of it. That is
deliberate: a leaf written on the line before a paragraph would otherwise swallow the paragraph.

```markdown
::image[Chart]{src="a.png"}
The paragraph below is not part of the leaf.
```

## Names

```php
DirectiveSyntax::NAME  // '[A-Za-z][A-Za-z0-9_-]*'
```

A letter, then letters, digits, `_` and `-`. A name may not start with a digit, and `.` and `:`
are not name characters — so `::2fast` is text, not a directive.

There are **no namespace prefixes in document syntax**. `w:toc` is not a directive; it is text.
Attribute *keys* may contain `:` and `-`, which is a different thing:

```markdown
::note{data-x=1 xml:lang="en"}
```

parses, with two attributes: `data-x` and `xml:lang`.

## Labels

One pair of square brackets, and they do not nest:

```php
DirectiveSyntax::LABEL  // '(?:\[([^\]\[]*)\])?'
```

A `[` inside a label has no closing partner, so the directive is left as literal text — which is
the recoverable outcome. There is no escape character anywhere in this grammar, so there is no
way to write a `]` in a label.

## Attributes

Four forms, and they are all of them:

```text
{width=50%}          bare value, runs to whitespace
{title="a b c"}      double-quoted, so spaces survive
{title='a "b" c'}    single-quoted, so double quotes survive
{wide}               no value at all — present and true
{#lead .terse}       shorthand: # sets id, . appends to class
```

Three edge cases that catch people out:

- **`{wide=}` is the empty string, not `true`.** `{wide}` is `true`; `{wide=}` is `''`. A bare key
  with no equals sign is the one that means true.
- **No whitespace around `=`.** `{a = 1}` is two bare attributes called `a` and `1`, both true.
- **A bare value stops at whitespace, `{` or `}`.** `{expr=a=b}` is one attribute whose value is
  `a=b`.

Shorthands may also be written bare after the name, and must touch the braces:

```markdown
::note#lead.terse{title="x"}   parses: id=lead, class=terse, title=x
::note #lead .terse {title="x"} does not parse
```

Where both are given, the braces win: `{#fromBraces}` beats a bare `#fromBare`.

## Fences

A container closes on a line of **at least as many** colons as opened it:

```markdown
:::: outer
::: inner
:::
::::
```

Four colons to open, three nested, three to close the inner, four to close the outer. A `:::`
inside does not close a `::::`.

The opening line may also end in colons, which mean nothing — as long as something else is on the
line, because `:::` alone is how a container closes:

```markdown
:::::::::::: SPOILER ::::::::::::We're going to spoil it in three
easy steps:
::::::::::::::::::::::::::::::::::
```

A container with **no name** is legal, and only with three or more colons:

```markdown
::: {.myClass}
some markdown
:::
```

The name has to be there for a leaf, because a leaf has nowhere to put the name otherwise — an
empty element in place of what the author wrote is not a recoverable outcome. `:: {.myClass}` is
literal text.

## What is deliberately not a directive

- `Note: prose` — a colon before a space
- `3:2`, `10:30` — a colon between digits
- `status:error` — a word before the colon
- `    :::a` — four spaces of indent, which is a code block in CommonMark
- anything inside a fenced or indented code block

The fence case is structural rather than a list: CommonMark does not look for new block starts
inside a block that is not a container, so a `:::` inside a fence is code before this extension
is ever consulted.

## The one thing worth knowing before you rely on it

**`__toString()` does not round-trip a value containing a double quote.** It writes `\"`, and the
reader has no escape handling, so the value comes back split:

```php
in  : {q="say \"hi\""}
out : {q="say \"hi\""}
back: {q="say \"", hi=true}      ← corrupted
```

The package documents the round trip on its own example, which has no quotes in it and does round
trip. The general claim is not true, and this page says so rather than repeating it.

{% endraw %}
