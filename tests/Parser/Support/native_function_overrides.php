<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Tests\Parser\Support\NativeFunctions;

/**
 * Namespaced override of function_exists() for the parser support classes.
 *
 * Unqualified function_exists() calls inside the Atelier\Diagram\Parser\Support
 * namespace resolve to this function first, letting tests hide native functions
 * (notably the mbstring family) to cover the byte-oriented fallback branches.
 * It delegates to the global function unless a name is hidden via
 * NativeFunctions::hide().
 */
function function_exists(string $function): bool
{
    if (NativeFunctions::isHidden($function)) {
        return false;
    }

    return \function_exists($function);
}
