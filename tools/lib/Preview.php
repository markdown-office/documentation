<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * Turns a built document into page images, by asking the tool that draws it.
 *
 * A Word document is drawn by WordCraft and a deck by DeckCraft — the two clean-room
 * reimplementations of Word and PowerPoint. Neither is a dependency of the suite: they are here
 * because a page of screenshots nobody regenerated is a page that goes stale without anyone
 * noticing. When a tool is missing, or present and broken, the answer is a document with no
 * preview image rather than a failed build. The `.docx` *is* the product; the preview is a
 * courtesy.
 *
 * They are also quick. A conversion takes a fraction of a second, and the fonts they draw with
 * ship with them, so a document looks the same on the machine that drew it as on the machine that
 * built it — which is what makes a picture of a document worth publishing.
 *
 * The PDF one of them writes is the file the page offers as a download, and the page images are
 * read out of it: one rendering, and a set of files that agree with each other.
 *
 * Every command here has a deadline, and one that passes it is killed rather than waited on: a
 * tool that hangs must hang its own step, not the whole build.
 */
final class Preview
{
    private const DPI = 120;
    private const SECONDS = 120;

    /**
     * The tool that draws each kind of document, and the name it is called by.
     *
     * @var array<string, array{command: string, name: string}>
     */
    private const DRAWERS = [
        'docx' => ['command' => 'wordcraft-cli', 'name' => 'WordCraft'],
        'pptx' => ['command' => 'deckcraft-cli', 'name' => 'DeckCraft'],
    ];

    /**
     * The command that draws each kind of document, asked once because asking is the cost.
     *
     * @var array<string, string|null>
     */
    private array $tools = [];

    /**
     * What is missing, or broken, said once at the end of the build rather than once per example.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $missing = [];

        foreach (self::DRAWERS as $extension => $drawer) {
            if ($this->tool($extension) === null) {
                $missing[] = \sprintf(
                    '%s, so no %s example has a preview image',
                    $drawer['name'],
                    $extension,
                );
            }
        }

        if (self::python() === null) {
            $missing[] = 'python3 with PyMuPDF, so no PDF becomes page images';
        }

        return $missing;
    }

    /**
     * The page images for a document, or nothing at all if this machine cannot produce them.
     *
     * @return array{pdf: string, pages: list<string>}
     */
    public function pagesOf(string $document): array
    {
        $extension = \strtolower(\pathinfo($document, \PATHINFO_EXTENSION));
        $tool = $this->tool($extension);

        if ($tool === null) {
            return ['pdf' => '', 'pages' => []];
        }

        $pdf = $this->pdfFrom($document, $tool);

        if ($pdf === '') {
            return ['pdf' => '', 'pages' => []];
        }

        return ['pdf' => $pdf, 'pages' => $this->imagesOf($pdf)];
    }

    /**
     * The PDF a tool writes for a document.
     *
     * Both tools take the same two arguments and write the same kind of file, and the PDF is
     * what the page offers as a download as well as what the page images are read out of.
     */
    private function pdfFrom(string $document, string $tool): string
    {
        $target = \dirname($document) . '/' . \pathinfo($document, \PATHINFO_FILENAME) . '.pdf';

        @\unlink($target);

        $this->run(\sprintf(
            '%s convert %s %s',
            \escapeshellarg($tool),
            \escapeshellarg($document),
            \escapeshellarg($target),
        ), self::SECONDS);

        return \is_file($target) ? $target : '';
    }

    /**
     * The page images of a PDF.
     *
     * @return list<string>
     */
    private function imagesOf(string $pdf): array
    {
        $python = self::python();

        if ($python === null) {
            return [];
        }

        $directory = \dirname($pdf) . '/pages';
        @\mkdir($directory, 0o777, true);

        foreach (\glob($directory . '/page-*.png') ?: [] as $stale) {
            @\unlink($stale);
        }

        // PyMuPDF rather than ImageMagick: it reads the PDF itself, so the page count and the
        // page images cannot disagree, and Ghostscript does not have to be installed.
        $script = \sprintf(
            'import sys, pathlib, pymupdf'
            . "\n"
            . 'out = pathlib.Path(sys.argv[2])'
            . "\n"
            . 'with pymupdf.open(sys.argv[1]) as d:'
            . '    [p.get_pixmap(dpi=%d).save(out / ("page-%%02d.png" %% (n + 1))) for n, p in enumerate(d)]',
            self::DPI,
        );

        $this->run(
            \sprintf(
                '%s -c %s %s %s',
                \escapeshellarg($python),
                \escapeshellarg($script),
                \escapeshellarg($pdf),
                \escapeshellarg($directory),
            ),
            60,
        );

        $pages = \glob($directory . '/page-*.png') ?: [];
        \sort($pages);

        return \array_values($pages);
    }

    /**
     * The command that draws a kind of document, or null when this machine has none.
     *
     * The tool is looked for on the path and then asked for its version: a command that cannot
     * answer cannot draw either, and a broken tool is a tool the build must not wait on.
     */
    private function tool(string $extension): ?string
    {
        if (\array_key_exists($extension, $this->tools)) {
            return $this->tools[$extension];
        }

        $command = self::DRAWERS[$extension]['command'] ?? null;
        $found = $command === null ? null : self::which($command);

        if ($found !== null && ! $this->succeeds(\escapeshellarg($found) . ' --version')) {
            $found = null;
        }

        return $this->tools[$extension] = $found;
    }

    private static function python(): ?string
    {
        foreach (['python3', 'python'] as $name) {
            $found = self::which($name);

            if ($found === null) {
                continue;
            }

            if (self::succeeds(\escapeshellarg($found) . ' -c "import pymupdf" 2>&1')) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The path of a program on `PATH`, or null.
     */
    private static function which(string $name): ?string
    {
        $found = \trim(self::capture(\sprintf('command -v %s 2>/dev/null', \escapeshellarg($name))));

        return $found === '' ? null : $found;
    }

    /**
     * Whether a command finished cleanly.
     *
     * A check rather than a timeout, so it is only ever used for something that answers
     * immediately. Anything that might not answer is {@see self::run()}'s business.
     */
    private static function succeeds(string $command): bool
    {
        $pipes = [];
        $process = \proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! \is_resource($process)) {
            return false;
        }

        \stream_get_contents($pipes[1]);
        \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        return \proc_close($process) === 0;
    }

    /**
     * Everything a command wrote to either stream.
     */
    private static function capture(string $command): string
    {
        $pipes = [];
        $process = \proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! \is_resource($process)) {
            return '';
        }

        $output = (string) \stream_get_contents($pipes[1]) . (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        return $output;
    }

    /**
     * A command with a deadline, killed rather than waited on when it passes it.
     *
     * @return array{code: int, output: string, timedOut: bool}
     */
    private function run(string $command, int $seconds): array
    {
        $pipes = [];
        $process = \proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! \is_resource($process)) {
            return ['code' => 1, 'output' => '', 'timedOut' => false];
        }

        \stream_set_blocking($pipes[1], false);
        \stream_set_blocking($pipes[2], false);

        $output = '';
        $deadline = \microtime(true) + $seconds;
        $open = [1 => $pipes[1], 2 => $pipes[2]];

        while ($open !== []) {
            $ready = $open;
            $write = null;
            $except = null;
            $wait = \max(0, (int) (($deadline - \microtime(true)) * 1_000_000));

            if (@\stream_select($ready, $write, $except, 0, \min($wait, 200_000)) === false) {
                break;
            }

            foreach ($ready as $key => $stream) {
                $chunk = \fread($stream, 8192);

                if ($chunk === false || $chunk === '') {
                    if (\feof($stream)) {
                        unset($open[$key]);
                    }

                    continue;
                }

                $output .= $chunk;
            }

            if (\microtime(true) >= $deadline) {
                \proc_terminate($process, 9);
                \proc_close($process);

                return ['code' => 124, 'output' => $output, 'timedOut' => true];
            }
        }

        foreach ($open as $stream) {
            \fclose($stream);
        }

        return ['code' => \proc_close($process), 'output' => $output, 'timedOut' => false];
    }
}
