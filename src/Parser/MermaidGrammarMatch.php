<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Parser\Support\HeaderMatch;

/**
 * Grammar selected by header dispatch, with the header line offset.
 */
final readonly class MermaidGrammarMatch
{
    public function __construct(
        public MermaidGrammar $grammar,
        public HeaderMatch $header,
    ) {
    }
}
