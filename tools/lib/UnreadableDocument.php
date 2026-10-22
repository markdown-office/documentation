<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * The document an example names could not be read.
 *
 * Three things can go wrong and they are one thing to whoever reads the build: the fence named
 * a document, and the build could not use it. The message says which — there is no document at
 * that path, the document is outside the site, or the library could not read it.
 */
final class UnreadableDocument extends \RuntimeException
{
}
