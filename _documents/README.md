# The documents the site's examples read

An example that builds a document carries its Markdown in the fence. An example that *reads* a
document cannot: a `.docx` is a binary, and a Markdown fence is text.

So the documents are files, and they live here. A fence names one by its path, relative to the
site's own directory:

````markdown
{% example "A Word document read back to Markdown" %}
```word md
_documents/quarterly-report.docx
```
{% endexample %}
````

`quarterly-report.docx` is the one the [round-trip guide](https://markdown-office.github.io/guides/round-trip/)
reads back into Markdown. `tools/make-input-document.php` writes it.

It is committed rather than built, because it is an input and not an output: it is the one file
on this site that must not change when the library does, or a reader comparing what the library's
reader makes of it has nothing to compare against.

It was written with PHPWord rather than with the library this site documents, because an example
that reads a document the same library wrote proves less than one that reads a document from
somewhere else. The document is otherwise ordinary — a heading, a paragraph with emphasis and
inline code and a link, a table, a list, a quote and a code block — because an example that
shows the reader handling nothing is not an example.
