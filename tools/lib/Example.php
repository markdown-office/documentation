<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * A fenced block in a page that names a renderer, and the files that renderer produced.
 *
 * The point of the type is that an example on this site is not a picture of an example: it is
 * the output of running the block's own text through the library it documents, on the machine
 * that built the page. A documented behaviour that has stopped being true shows up as a build
 * that fails rather than as prose that is quietly wrong.
 *
 * Every path here is a *site URL* rather than a place on the build machine, because these values
 * end up in `src` attributes and in `href`s. Keeping the two apart is what lets the artifacts
 * live wherever the build likes — including a directory that is deleted afterwards — without a
 * page ending up pointing at `/Users/somebody/…`.
 */
final class Example
{
    /**
     * @param list<string> $previews
     */
    public function __construct(
        public readonly string $language,
        public readonly string $source,
        public readonly string $name,
        public readonly ?string $document = null,
        public readonly ?string $pdf = null,
        public readonly array $previews = [],
        public readonly ?string $failure = null,
        public readonly array $contents = [],
    ) {
    }

    /**
     * The pages of the built document, as text read back out of it.
     *
     * Shown in place of a page image on a machine that could not produce one. It is the fallback
     * rather than a consolation prize: it is read from the artifact, so a converter that dropped
     * something shows up here as something missing rather than as nothing at all.
     *
     * @return list<array{part: string, heading: string, lines: list<string>}>
     */
    public function outline(): array
    {
        return $this->contents;
    }

    public function hasOutline(): bool
    {
        return $this->contents !== [];
    }

    public function built(): bool
    {
        return $this->failure === null;
    }

    public function previewCount(): int
    {
        return \count($this->previews);
    }

    public function label(): string
    {
        return $this->name;
    }

    /**
     * The download links: the document itself, and the PDF when there is one.
     *
     * The document is the product — it is what the page is documenting, and a reader who wants
     * to try the example should get the example rather than a picture of it. The PDF is offered
     * beside it because it is the form that opens anywhere, and on a machine with no Office at
     * all it is the only one that will open.
     *
     * @return list<array{label: string, href: string, suffix: string}>
     */
    public function downloads(): array
    {
        $links = [];

        if ($this->document !== null) {
            $links[] = ['label' => $this->documentName(), 'href' => $this->document, 'suffix' => 'source'];
        }

        if ($this->pdf !== null) {
            $links[] = ['label' => 'PDF', 'href' => $this->pdf, 'suffix' => 'PDF'];
        }

        return $links;
    }

    public function hasDownloads(): bool
    {
        return $this->downloads() !== [];
    }

    /**
     * The link markup, or nothing when there is nothing to link to.
     *
     * A failed example has no files, and a successful one always has the document, so the empty
     * case is only reachable when a build was interrupted — which is why it returns a string the
     * caller can print unconditionally.
     */
    public function downloadsHtml(): string
    {
        $links = '';

        foreach ($this->downloads() as $download) {
            $links .= \sprintf(
                '<a class="example__download" href="%s" download><span>%s</span>'
                . '<abbr title="%s">%s</abbr></a>',
                $this->escape($download['href']),
                $this->escape($download['label']),
                $this->escape($this->describe($download['href'])),
                $this->escape($download['suffix']),
            );
        }

        return $links;
    }

    /**
     * The file name to offer, which is the name it has on the build machine.
     *
     * Preserved rather than renamed to the example's title, because a `.docx` called
     * `getting-started.docx` is recognisable in a downloads folder and `a3f9c1e2.docx` is not.
     */
    private function documentName(): string
    {
        return $this->document === null ? '' : \basename($this->document);
    }

    /**
     * A sentence saying what the file opens in, which is more use in a `title` attribute than a
     * file extension is.
     *
     * `match` rather than `\match`: a leading backslash makes PHP lex it as a namespaced name
     * rather than the keyword, and the whole file stops parsing. Every other global call in this
     * project is fully qualified; this one is not, and the reason is the backslash.
     */
    private function describe(string $path): string
    {
        return match (\strtolower(\pathinfo($path, \PATHINFO_EXTENSION))) {
            'docx' => 'A Word document, editable in Word, LibreOffice and Google Docs',
            'pptx' => 'A PowerPoint presentation, editable in PowerPoint, Keynote and LibreOffice',
            'pdf' => 'The same document as a PDF, for reading without Office',
            default => 'The file this example built',
        };
    }

    private function escape(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}