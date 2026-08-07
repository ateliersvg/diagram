<?php

declare(strict_types=1);

namespace Atelier\Diagram\Er;

use Atelier\Diagram\Exception\InvalidArgumentException;

enum ErCardinality: string
{
    case ZeroOrOne = '|o';
    case ExactlyOne = '||';
    case ZeroOrMore = 'o{';
    case OneOrMore = '|{';

    public static function fromToken(string $token): self
    {
        return self::tryFrom($token) ?? throw new InvalidArgumentException(\sprintf('Unsupported ER cardinality "%s".', $token));
    }
}
