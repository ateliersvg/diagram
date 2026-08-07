<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

/**
 * Shared regex fragments for Mermaid-like identifiers.
 *
 * Fragments are intentionally not wrapped with delimiters so parsers can
 * compose them inside larger statement patterns.
 */
final class IdentifierPattern
{
    public const string STRICT = '[A-Za-z_][A-Za-z0-9_]*';

    public const string TOKEN = '\S+';

    private function __construct()
    {
    }
}
