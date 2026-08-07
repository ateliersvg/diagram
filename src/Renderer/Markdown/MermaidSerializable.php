<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;

/**
 * Shared validation for Mermaid serializer output.
 *
 * @internal
 */
final class MermaidSerializable
{
    private function __construct()
    {
    }

    public static function strictId(string $id, string $diagram, string $what): void
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $id)) {
            throw new InvalidArgumentException(\sprintf('Cannot render %s to Mermaid: %s "%s" is not a supported identifier.', $diagram, $what, $id));
        }
    }

    public static function fieldName(string $name, string $diagram): void
    {
        if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException(\sprintf('Cannot render %s to Mermaid: field name "%s" is not a supported identifier.', $diagram, $name));
        }
    }

    public static function token(string $token, string $diagram, string $what): void
    {
        if (1 !== preg_match('/^\S+$/', $token)) {
            throw new InvalidArgumentException(\sprintf('Cannot render %s to Mermaid: %s "%s" is not a supported token.', $diagram, $what, $token));
        }
    }

    public static function text(string $text, string $diagram, string $what, string $unsupportedPattern, string $unsupportedMessage): void
    {
        if (1 === preg_match($unsupportedPattern, $text)) {
            throw new InvalidArgumentException(\sprintf('Cannot render %s to Mermaid: %s %s.', $diagram, $what, $unsupportedMessage));
        }

        if ('' === trim($text)) {
            throw new InvalidArgumentException(\sprintf('Cannot render %s to Mermaid: %s must not be empty.', $diagram, $what));
        }
    }
}
