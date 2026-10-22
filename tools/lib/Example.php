<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * A fenced block in a page that names a direction, and the files that direction produced.
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
     * @param string      $language Which library drew the example: `word` or `presentation`.
     * @param string      $role     What the document is to the example: `output` for one it
     *                               built, `source` for one it read.
     * @param list<string> $previews
     */
    public function __construct(
        public readonly string $language,
        public readonly string $role,
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

    public function label(): string
    {
        return $this->name;
    }
}
