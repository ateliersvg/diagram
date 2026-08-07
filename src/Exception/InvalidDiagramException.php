<?php

declare(strict_types=1);

namespace Atelier\Diagram\Exception;

/**
 * Exception thrown when a diagram model is semantically invalid.
 *
 * Typical cases: duplicate state id, merge from an unknown branch, more
 * than 3 Venn sets. The message must name the offending element.
 */
final class InvalidDiagramException extends InvalidArgumentException
{
}
