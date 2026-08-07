<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\ParserInputLimits;

/**
 * @template-covariant T of DiagramModel
 *
 * @internal
 */
interface MermaidDiagramParserInterface
{
    /**
     * @return T
     *
     * @throws ParseException
     */
    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): DiagramModel;
}
