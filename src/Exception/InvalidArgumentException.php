<?php

declare(strict_types=1);

namespace Atelier\Diagram\Exception;

/**
 * Exception thrown when invalid arguments are passed to library methods.
 *
 * Typical cases: empty strings where a value is required, negative
 * dimensions, out-of-range opacity values.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
