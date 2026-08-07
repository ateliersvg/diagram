<?php

declare(strict_types=1);

namespace Atelier\Diagram\Requirement;

use Atelier\Diagram\Exception\InvalidArgumentException;

enum RequirementRelationshipKind: string
{
    case Contains = 'contains';
    case Copies = 'copies';
    case Derives = 'derives';
    case Satisfies = 'satisfies';
    case Verifies = 'verifies';
    case Refines = 'refines';
    case Traces = 'traces';

    public static function fromToken(string $token): self
    {
        return match ($token) {
            self::Contains->value => self::Contains,
            self::Copies->value => self::Copies,
            self::Derives->value => self::Derives,
            self::Satisfies->value => self::Satisfies,
            self::Verifies->value => self::Verifies,
            self::Refines->value => self::Refines,
            self::Traces->value => self::Traces,
            default => throw new InvalidArgumentException(\sprintf('Unsupported requirement relationship kind "%s".', $token)),
        };
    }

    public static function pattern(): string
    {
        return implode('|', array_map(static fn (self $kind): string => preg_quote($kind->value, '/'), self::cases()));
    }
}
