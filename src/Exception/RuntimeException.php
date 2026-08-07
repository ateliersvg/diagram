<?php

declare(strict_types=1);

namespace Atelier\Diagram\Exception;

/**
 * Exception thrown for runtime errors that valid input cannot prevent.
 *
 * Typical cases: file system failures, unexpected states during rendering.
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
