<?php

declare(strict_types=1);

namespace Atelier\Diagram\Exception;

/**
 * Marker interface implemented by every exception thrown by atelier/diagram.
 *
 * Catch this interface to handle all library errors uniformly:
 *
 * ```php
 * try {
 *     $diagram = Diagram::fromMermaid($source);
 * } catch (ExceptionInterface $e) {
 *     // any atelier/diagram error
 * }
 * ```
 */
interface ExceptionInterface extends \Throwable
{
}
