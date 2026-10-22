#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Builds every example on the site with the library it documents, before Eleventy runs.
 *
 *   php tools/render-examples.php            build the examples
 *   php tools/render-examples.php --strict   ... and exit non-zero if one did not build
 *
 * A page carries an example as a fence tagged with the direction it runs in, between a pair of
 * tags:
 *
 *     {% example "A titled Word document" %}
 *     ```md word
 *     # Notes from the review
 *     ```
 *     {% endexample %}
 *
 * The tag says where the example starts and where it ends: `md word` builds a Word document
 * from Markdown, `md presentation` builds a deck, and `word md` reads a Word document back
 * into Markdown. The body of the fence is the input — the Markdown for the first two, the
 * path of the document for the last.
 *
 * This script finds those, hands the fence's own text to `MarkdownToWord`,
 * `PresentationRenderer` or `WordToMarkdown`, and writes three things: the document, a PDF and
 * a page image or two produced by the tool that draws that kind of document, and a line in
 * `_data/examples.json` naming them.
 *
 * It writes *facts*, not markup. The HTML around an example is built by the Eleventy shortcode
 * that reads this manifest, so there is one place that decides what an example looks like and it
 * is the same place that decides what every other part of a page looks like.
 *
 * Nothing here is cached in a way that can go stale: an example is keyed by the hash of its own
 * source, so an unchanged block is not rebuilt and a changed one always is.
 */

\error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);
\ini_set('display_errors', 'stderr');

if (\PHP_SAPI !== 'cli') {
    \fwrite(\STDERR, "The examples are built from the command line.\n");
    exit(1);
}

$root = \dirname(__DIR__);

foreach ([
    $root . '/vendor/autoload.php',
    __DIR__ . '/lib/Autoload.php',
] as $required) {
    if (! \is_file($required)) {
        \fwrite(\STDERR, \sprintf('Missing "%s". Run `composer install` in documentation/.' . "\n", $required));
        exit(1);
    }

    require_once $required;
}

use MarkdownOffice\Documentation\Tool\Preview;
use MarkdownOffice\Documentation\Tool\Runner;

$contentDirectory = $root . '/content';
$artifacts = $root . '/_examples';
$manifest = $root . '/_data/examples.json';
$strict = \in_array('--strict', \array_slice($argv, 1), true);

/**
 * One example, before it has been built.
 */
final class Pending
{
    public function __construct(
        public readonly string $file,
        public readonly string $hash,
        public readonly string $direction,
        public readonly string $name,
        public readonly string $source,
    ) {}
}

/**
 * Every `{% example %}` … `{% endexample %}` pair in the content, with the fence inside it.
 *
 * @return list<Pending>
 */
function find(string $directory): array
{
    $found = [];

    $files = \iterator_to_array(
        new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        ),
        false,
    );

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (! $file->isFile() || ! \str_ends_with($path, '.md')) {
            continue;
        }

        $markdown = (string) \file_get_contents($path);

        // Paired tags rather than a fence on its own: the fence has to stay a fence so the
        // Markdown parser still reads it as one, and a page that shows its own examples needs a
        // way to say "this one is code and that one is a document".
        \preg_match_all(
            '/\{%\s*example\b([^%]*)%\}(.*?)\{%\s*endexample\s*%\}/s',
            $markdown,
            $matches,
            \PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $body = $match[2];

            // A fence of three or more backticks, tagged with the direction it runs in —
            // `md word`, `md presentation` or `word md` — closed by a run of the same length.
            // The same length is what lets an example that itself contains a fenced block be
            // written with four backticks.
            //
            // A quoted title may follow the direction on the fence line. It is the same title the
            // paired tag can carry, and a page that writes the title once in the fence and once
            // in the tag is a page with two places to keep them in step.
            \preg_match(
                '/^(`{3,})(md|word|presentation)[ \t]+(md|word|presentation)[ \t]*(?:"([^"]*)")?[ \t]*\n([\s\S]*?)^\1[ \t]*$/m',
                $body,
                $fence,
            );

            if ($fence === []) {
                \fwrite(\STDERR, \sprintf(
                    "  %s: an {%% example %%} block has no `md word`, `md presentation` or `word md` fence.\n",
                    $file,
                ));
                continue;
            }

            $direction = $fence[2] . '-' . $fence[3];

            if (! isset(Runner::DIRECTIONS[$direction])) {
                \fwrite(\STDERR, \sprintf(
                    "  %s: an {%% example %%} block runs in the direction \"%s\", which the site does not build.\n",
                    $file,
                    $direction,
                ));
                continue;
            }

            // What goes to the library is the body of the fence, without its backticks: the
            // Markdown for a direction that builds a document, the path of the document for one
            // that reads it.
            //
            // The trim is load-bearing for the document as well as for the key: a deck's
            // frontmatter is matched at the first byte of the file, so an example whose source
            // began with a blank line would put `theme:` on slide 2 and be refused for naming a
            // deck directive there.
            $source = \trim($fence[5]);

            // The tag wins over the fence, because the tag is the one an author edits when they
            // are looking at the `{% example %}` and not at the fence inside it.
            $title = \trim(\trim($match[1]), '"');
            $title = $title === '' ? \trim($fence[4] ?? '', '"') : $title;
            $title = $title !== '' ? $title : Runner::DIRECTIONS[$direction] . ' example';

            // The key is hashed from the *whole* trimmed block, fences included, because that is
            // what the Eleventy shortcode has to hand: it is given the text between the paired
            // tags and cannot tell a body from the fence around it without re-parsing it.
            $block = \trim($fence[0]);

            $found[] = new Pending(
                \str_replace($directory . '/', '', $path),
                \substr(\hash('sha256', $block), 0, 16),
                $direction,
                $title,
                $source,
            );
        }
    }

    return $found;
}

/**
 * The examples this build should not have, described rather than thrown.
 *
 * An example exists to demonstrate something, and one that has to be switched off is a page
 * teaching a feature that is not there. Saying so is more useful than silently dropping it.
 *
 * @return list<array{hash: string, reason: string, source: string, language: string, role: string, name: string}>
 */
function skipped(string $directory): array
{
    $file = $directory . '/.skipped.json';

    if (! \is_file($file)) {
        return [];
    }

    $read = \json_decode((string) \file_get_contents($file), true);

    return \is_array($read) ? \array_values(\array_filter($read, \is_array(...))) : [];
}

$pending = find($contentDirectory);
$skipped = skipped($contentDirectory);
// One Preview for the whole build, not one per call: it caches whether this machine can
// convert anything, and a second instance would answer "I don't know" and probe again for
// every example.
$preview = new Preview();
$runner = new Runner($artifacts, '/examples', $preview, $root);

$manifestDirectory = \dirname($manifest);
@\mkdir($manifestDirectory, 0o777, true);
@\mkdir($artifacts, 0o777, true);

$entries = [];
$problems = [];

foreach ($skipped as $entry) {
    $hash = (string) ($entry['hash'] ?? \hash('sha256', ($entry['language'] ?? '') . "\0" . ($entry['source'] ?? '')));

    $entries[$hash] = [
        'language' => (string) ($entry['language'] ?? ''),
        'role' => (string) ($entry['role'] ?? Runner::ROLE_OUTPUT),
        'name' => (string) ($entry['name'] ?? ''),
        'source' => (string) ($entry['source'] ?? ''),
        'document' => null,
        'pdf' => null,
        'previews' => [],
        'contents' => [],
        'failure' => null,
        'skipped' => (string) ($entry['reason'] ?? 'not built'),
    ];

    \printf("  skipped %s: %s\n", $hash, (string) ($entry['reason'] ?? 'not built'));
}

foreach ($pending as $example) {
    $built = $runner->run($example->direction, $example->hash, $example->name, $example->source);

    $entries[$example->hash] = [
        'language' => $built->language,
        'role' => $built->role,
        'name' => $built->label(),
        'source' => $built->source,
        'document' => $built->document,
        'pdf' => $built->pdf,
        'previews' => $built->previews,
        'contents' => $built->outline(),
        'failure' => $built->failure,
        'skipped' => null,
    ];

    if ($built->failure !== null) {
        $problems[] = $built->failure;
    }
}

\ksort($entries);
\file_put_contents(
    $manifest,
    (string) \json_encode($entries, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n",
);

$counts = $runner->counts();

\printf(
    "%d example(s): %d built, %d reused. Manifest at _data/examples.json.\n",
    \count($entries),
    $counts['rendered'],
    $counts['reused'],
);

foreach ($preview->missing() as $missing) {
    \printf("  note: the examples have no page image, because this machine has no %s.\n", $missing);
}

foreach ($problems as $problem) {
    \fwrite(\STDERR, '  problem: ' . $problem . "\n");
}

if ($problems !== [] && $strict) {
    \fwrite(\STDERR, \sprintf("\n%d example(s) did not build, and --strict was asked for.\n", \count($problems)));
    exit(1);
}