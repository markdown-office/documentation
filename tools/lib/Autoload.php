<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * An autoloader for the classes in this directory.
 *
 * The documentation site is built by Eleventy and this is the part it cannot do, so there is no
 * Composer package here and no autoloader to configure — just a namespace, a directory, and the
 * two packages the examples are built with already on the include path.
 */
\spl_autoload_register(static function (string $class): void {
    $prefix = 'MarkdownOffice\\Documentation\\Tool\\';

    if (! \str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . \str_replace('\\', '/', \substr($class, \strlen($prefix))) . '.php';

    if (\is_file($file)) {
        require_once $file;
    }
});
