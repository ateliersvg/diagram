<?php

declare(strict_types=1);

namespace Atelier\Diagram\Requirement;

use Atelier\Diagram\Exception\InvalidArgumentException;

enum RequirementNodeKind: string
{
    case Requirement = 'requirement';
    case Element = 'element';

    public static function fromKeyword(string $keyword): self
    {
        return match ($keyword) {
            self::Requirement->value => self::Requirement,
            self::Element->value => self::Element,
            default => throw new InvalidArgumentException(\sprintf('Unsupported requirement diagram node kind "%s".', $keyword)),
        };
    }
}
