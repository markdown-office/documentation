<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;
use MarkdownWord\MarkdownToWord;
use MarkdownWord\Parser\CommonMarkParser;
use MarkdownPresentation\Document\DeckParser;
use MarkdownPresentation\Render\PresentationRenderer;

/**
 * Runs a page's example through the library it documents, and keeps what came out.
 *
 * Caching is by content hash rather than by page or by position, so the second page to show the
 * same example pays nothing, a build that changes one paragraph re-renders one example, and a
 * build that changes none re-renders none. The cost that matters is LibreOffice — several
 * seconds a document — so this is the difference between a ten-second build and a five-minute
 * one on an unchanged tree.
 *
 * Artifacts are written under the site's own root, which is why the URLs handed back are
 * relative to the site: the build cleans its output by deleting one directory, and there is no
 * second copy of a preview anywhere that could survive that.
 */
final class Runner
{
    public const LANGUAGES = ['word', 'presentation'];

    private int $rendered = 0;
    private int $reused = 0;

    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        private readonly string $root,
        private readonly string $prefix,
        private readonly Preview $preview,
        private readonly Outline $outline = new Outline(),
    ) {}

    /**
     * @return list<string> the message from every example that would not build.
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * @return array{rendered: int, reused: int}
     */
    public function counts(): array
    {
        return ['rendered' => $this->rendered, 'reused' => $this->reused];
    }

    /**
     * Builds one example, or says why it could not be built.
     *
     * The slug is the content hash, so the same source on two pages is one document rather than
     * two, and changing an example leaves the old artifacts behind rather than overwriting an
     * unrelated example that happens to sit at the same path.
     *
     * `$root` is the directory that is published at `$prefix`, so the two are separate: the build
     * keeps its artifacts in `_examples/` and Eleventy copies that to `/examples` in the site,
     * and neither half has to know where the other put its files.
     */
    public function run(string $language, string $slug, string $name, string $source): Example
    {
        $cache = \sprintf('%s/%s/%s', $this->root, $language, $slug);
        $url = \sprintf('%s/%s/%s', $this->prefix, $language, $slug);
        $sourceFile = $cache . '/example.md';
        $manifest = $cache . '/manifest.json';

        if (\is_file($sourceFile) && \file_get_contents($sourceFile) === $source && \is_file($manifest)) {
            $this->reused++;

            return $this->read($language, $url, $name, $cache);
        }

        foreach (\glob($cache . '/*') ?: [] as $stale) {
            $this->remove($stale);
        }

        @\mkdir($cache, 0o777, true);
        \file_put_contents($sourceFile, $source);

        try {
            $document = $language === 'word'
                ? $this->word($source, $cache . '/example.docx')
                : $this->presentation($source, $cache . '/example.pptx');
        } catch (\Throwable $error) {
            $message = \sprintf(
                'The %s example "%s" did not build: %s',
                $language === 'word' ? 'Word' : 'PowerPoint',
                $name,
                $this->oneLine($error->getMessage()),
            );

            $this->failures[] = $message;
            $this->manifest($cache, failure: $message);

            return new Example($language, $source, $name, failure: $message);
        }

        $rendered = $this->preview->pagesOf($document);
        $previews = [];

        foreach ($rendered['pages'] as $page) {
            $keep = $cache . '/' . \basename($page);

            if (@\copy($page, $keep)) {
                $previews[] = $url . '/' . \basename($keep);
            }
        }

        $pdf = $rendered['pdf'] === '' ? null : $url . '/' . \pathinfo($document, \PATHINFO_FILENAME) . '.pdf';

        // What is in the document, read back out of it. Shown when there is no page image to
        // show, and built either way so a machine that gains LibreOffice later does not have to
        // rebuild every example to get them.
        $contents = $this->outline->of($document);

        $this->rendered++;
        $this->manifest(
            $cache,
            document: $url . '/' . \basename($document),
            pdf: $pdf,
            previews: $previews,
            contents: $contents,
        );

        return new Example(
            $language,
            $source,
            $name,
            $url . '/' . \basename($document),
            $pdf,
            $previews,
            contents: $contents,
        );
    }

    /**
     * Builds a `.docx` at `$target` and answers with that path.
     *
     * `convertTo()` answers with the document's bytes rather than with where it put them, which
     * is the right answer for a caller holding the result in memory and the wrong one here: the
     * next thing this does is hand a path to LibreOffice.
     *
     * The parser is passed rather than left to the default, because the default does not read
     * frontmatter. `CommonMarkParser::DEFAULT_FLAVOUR` is GitHub-Flavored Markdown plus directives,
     * and a document opening with `---` and an `options:` block has that block rendered as body text:
     * silently, and with the configuration it was meant to carry ignored. `withAllExtensions()` is
     * not the answer either — it does read frontmatter, but it leaves directives out, so `::toc` and
     * `:footnote` stop being directives and become the text an author wrote.
     *
     * So both, named. That is what a pipeline reading configuration out of a document wants, and the
     * page on Word configuration explains why it is not the default.
     */
    private function word(string $markdown, string $target): string
    {
        (new MarkdownToWord($markdown, parser: new CommonMarkParser([
            GithubFlavoredMarkdownExtension::class,
            GenericDirectiveExtension::class,
            FrontMatterExtension::class,
        ])))->save($target);

        return $target;
    }

    private function presentation(string $markdown, string $target): string
    {
        $deck = (new DeckParser())->parse($markdown);
        (new PresentationRenderer())->render($deck, $target);

        return $target;
    }

    /**
     * Re-reads a cached example rather than rendering it again.
     *
     * The manifest is the record the last run wrote, not something re-derived from the files: a
     * cached example has to come back as exactly the set of files it was last time, or the cache
     * is not describing anything and the next build cannot trust it either.
     */
    private function read(string $language, string $url, string $name, string $cache): Example
    {
        /** @var array{document?: ?string, pdf?: ?string, previews?: list<string>, failure?: ?string} $read */
        $read = (array) \json_decode((string) \file_get_contents($cache . '/manifest.json'), true);

        return new Example(
            $language,
            (string) \file_get_contents($cache . '/example.md'),
            $name,
            $read['document'] ?? null,
            $read['pdf'] ?? null,
            $read['previews'] ?? [],
            $read['failure'] ?? null,
        );
    }

    /**
     * @param list<string> $previews
     */
    /**
     * @param list<string> $previews
     * @param list<array{part: string, heading: string, lines: list<string>}> $contents
     */
    private function manifest(
        string $cache,
        ?string $document = null,
        ?string $pdf = null,
        array $previews = [],
        ?string $failure = null,
        array $contents = [],
    ): void {
        \file_put_contents(
            $cache . '/manifest.json',
            (string) \json_encode(
                [
                    'document' => $document,
                    'pdf' => $pdf,
                    'previews' => $previews,
                    'contents' => $contents,
                    'failure' => $failure,
                ],
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    private function remove(string $path): void
    {
        if (\is_dir($path)) {
            foreach (\glob($path . '/*') ?: [] as $child) {
                $this->remove($child);
            }

            @\rmdir($path);

            return;
        }

        @\unlink($path);
    }

    private function oneLine(string $message): string
    {
        return \trim(\preg_replace('/\s+/', ' ', $message) ?? $message);
    }
}