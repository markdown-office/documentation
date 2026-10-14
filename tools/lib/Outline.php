<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * What is actually inside a built document, read out of the file rather than out of the Markdown.
 *
 * This is the fallback for a machine that cannot produce a page image. A picture is the best way
 * to show a document, and it needs a program this build cannot assume works. A page of downloads
 * with nothing under them is a poor page — it asks the reader to take the build's word for it.
 *
 * So when there is no image, this reads the text back out of the package the library just wrote:
 * `word/document.xml` for a `.docx`, `ppt/slides/slideN.xml` for a `.pptx`, both of which are
 * XML inside the zip. What it shows is therefore read from the artifact rather than from the
 * source that produced it, which is the whole point: a converter that dropped a heading shows up
 * here as a missing heading.
 *
 * A DOM rather than a regular expression, because the tags a paragraph starts with are also the
 * prefixes of tags that are not paragraphs — `<w:p>` opens a paragraph, `<w:pPr>` opens its
 * properties and `<w:pStyle>` names its style, and an expression that splits on `<w:p` finds all
 * three. The namespaces are looked up rather than assumed, so a document that declares them the
 * other way round still reads.
 */
final class Outline
{
    private const NAMESPACES = [
        'w' => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
    ];

    /**
     * @return list<array{part: string, heading: string, lines: list<string>}>
     */
    public function of(string $document): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($document) !== true) {
            return [];
        }

        try {
            return \str_ends_with($document, '.pptx')
                ? $this->slides($zip)
                : [$this->page($zip, 'word/document.xml', 'Document')];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{part: string, heading: string, lines: list<string>}>
     */
    private function slides(\ZipArchive $zip): array
    {
        $slides = [];

        for ($number = 1; ; $number++) {
            $name = \sprintf('ppt/slides/slide%d.xml', $number);

            if ($zip->locateName($name) === false) {
                break;
            }

            $page = $this->page($zip, $name, 'Slide ' . $number);
            // A slide's own first line is its title, and calling it "Slide 3" when it says
            // "Results" helps nobody. It is still labelled as a slide below, so nothing is lost.
            $page['heading'] = $page['lines'][0] ?? $page['heading'];
            $slides[] = $page;
        }

        return $slides;
    }

    /**
     * @return array{part: string, heading: string, lines: list<string>}
     */
    private function page(\ZipArchive $zip, string $part, string $heading): array
    {
        $xml = $zip->getFromName($part);

        if ($xml === false) {
            return ['part' => $part, 'heading' => $heading, 'lines' => []];
        }

        $document = new \DOMDocument();

        if (@$document->loadXML($xml) !== true) {
            return ['part' => $part, 'heading' => $heading, 'lines' => []];
        }

        $xpath = new \DOMXPath($document);

        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        // Word marks a paragraph with `w:p` and a run's text with `w:t`; a slide uses `a:p` and
        // `a:t` for the same two things. Both are asked for, because a slide's title placeholder
        // is the same shape as its body and neither should be lost.
        //
        // Iterated rather than merged into one array: `DOMXPath::query()` answers a
        // `DOMNodeList`, which `array_merge()` will not take, and a list of paragraphs is only
        // ever walked once.
        $paragraphs = [];

        foreach (['//w:body//w:p', '//a:p'] as $expression) {
            foreach ($xpath->query($expression) ?: [] as $paragraph) {
                $paragraphs[] = $paragraph;
            }
        }

        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $text = '';

            foreach ($xpath->query('.//w:t | .//a:t', $paragraph) ?: [] as $run) {
                $text .= $run->textContent;
            }

            $text = \trim(\preg_replace('/\s+/', ' ', $text) ?? $text);

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return ['part' => $part, 'heading' => $heading, 'lines' => \array_values(\array_unique($lines))];
    }
}