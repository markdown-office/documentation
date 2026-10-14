<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * Turns a built document into page images, by asking LibreOffice and PyMuPDF.
 *
 * Neither is a dependency of the suite — both are here because a page of screenshots nobody
 * regenerated is a page that goes stale without anyone noticing. When a converter is missing, or
 * present and broken, the answer is a document with no preview image rather than a failed build.
 * The `.docx` *is* the product; the preview is a courtesy.
 *
 * Two things that are true of `soffice` shape this class.
 *
 * The first is that it locks. A second `soffice` started while one is running attaches itself to
 * the first and writes into the first's directory, so every run gets a private profile and every
 * run has to be finished before the next one starts.
 *
 * The second is that it can die instead of returning. A crash leaves a core-dumping process with
 * no exit status and a pipe that never closes, and `proc_open()` waits for that forever. Every
 * command here therefore has a deadline, and a command past it is killed rather than waited on.
 * Without that a broken LibreOffice hangs the build instead of degrading it.
 */
final class Preview
{
    private const DPI = 120;
    private const SECONDS = 120;

    /** @var list<string> */
    private const LIBREOFFICE = [
        '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        '/usr/bin/soffice',
        '/usr/bin/libreoffice',
        '/usr/lib/libreoffice/program/soffice',
    ];

    /** Whether this machine can convert anything at all. Asked once, because asking is the cost. */
    private ?bool $usable = null;

    private ?string $reason = null;

    public function __construct(private readonly string $scratch) {}

    /**
     * What is missing, or broken, said once at the end of the build rather than once per example.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        if (self::libreoffice() === null) {
            return ['LibreOffice, so no example has a preview image'];
        }

        if ($this->usable === false && $this->reason !== null) {
            return [$this->reason];
        }

        if (self::python() === null) {
            return ['python3 with PyMuPDF, so no PDF becomes page images'];
        }

        return [];
    }

    /**
     * The page images for a document, or nothing at all if this machine cannot produce them.
     *
     * @return array{pdf: string, pages: list<string>}
     */
    public function pagesOf(string $document): array
    {
        if (! $this->canConvert()) {
            return ['pdf' => '', 'pages' => []];
        }

        $pdf = $this->pdfFrom($document);

        if ($pdf === '') {
            return ['pdf' => '', 'pages' => []];
        }

        return ['pdf' => $pdf, 'pages' => $this->imagesOf($pdf)];
    }

    /**
     * Whether this machine converts at all, found by converting the smallest thing there is.
     *
     * A probe rather than a check for the binary's existence, because LibreOffice can be
     * installed and still be unable to start — a crash on this machine reported `soffice
     * --version` perfectly and then aborted on the first real conversion. Existence is not
     * capability, and a build that discovers the difference once per example costs a minute each
     * time.
     */
    private function canConvert(): bool
    {
        if ($this->usable !== null) {
            return $this->usable;
        }

        $soffice = self::libreoffice();

        if ($soffice === null) {
            return $this->usable = false;
        }

        @\mkdir($this->scratch . '/probe', 0o777, true);
        $source = $this->scratch . '/probe/probe.txt';
        \file_put_contents($source, "probe\n");

        $profile = $this->scratch . '/lo-probe-' . \bin2hex(\random_bytes(6));
        $result = $this->run(\sprintf(
            '%s -env:UserInstallation=%s --headless --norestore --convert-to pdf --outdir %s %s',
            \escapeshellarg($soffice),
            \escapeshellarg('file://' . $profile),
            \escapeshellarg(\dirname($source)),
            \escapeshellarg($source),
        ), 90);

        $this->remove($profile);
        @\unlink($source);
        @\unlink(\dirname($source) . '/probe.pdf');

        if ($result['code'] === 0 && \is_file(\dirname($source) . '/probe.pdf')) {
            return $this->usable = true;
        }

        $this->reason = $result['timedOut']
            ? 'a working LibreOffice, which is installed but did not finish a one-line conversion'
            : 'a working LibreOffice, which is installed but failed to convert anything';

        return $this->usable = false;
    }

    private function pdfFrom(string $document): string
    {
        $soffice = self::libreoffice();
        $target = \dirname($document) . '/' . \pathinfo($document, \PATHINFO_FILENAME) . '.pdf';

        @\unlink($target);

        // A profile of its own per run, removed afterwards: LibreOffice leaves a few hundred
        // files of its own state in it and none of them should outlive the conversion.
        $profile = $this->scratch . '/lo-' . \bin2hex(\random_bytes(6));

        $this->run(\sprintf(
            '%s -env:UserInstallation=%s --headless --norestore --convert-to pdf --outdir %s %s',
            \escapeshellarg((string) $soffice),
            \escapeshellarg('file://' . $profile),
            \escapeshellarg(\dirname($document)),
            \escapeshellarg($document),
        ), self::SECONDS);

        $this->remove($profile);

        return \is_file($target) ? $target : '';
    }

    /**
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
            . "\n"
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

    private static function libreoffice(): ?string
    {
        foreach (self::LIBREOFFICE as $candidate) {
            if (\is_file($candidate) && \is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
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

        // `proc_close()` blocks until the child is gone, so the child is reaped here instead: the
        // pipes are drained while the process runs, and `proc_close()` afterwards returns at once
        // because there is nothing left to wait for.
        while ($open !== []) {
            $ready = $open;
            $write = null;
            $except = null;
            $wait = \max(0, (int) (($deadline - \microtime(true)) * 1_000_000));

            if (@\stream_select($ready, $write, $except, 0, \min($wait, 200_000)) === false) {
                break;
            }

            foreach ($ready as $stream) {
                $chunk = \fread($stream, 8192);

                if ($chunk === false || $chunk === '') {
                    if (\feof($stream)) {
                        unset($open[(int) $stream]);
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
}