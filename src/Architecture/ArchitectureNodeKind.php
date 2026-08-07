<?php

declare(strict_types=1);

namespace Atelier\Diagram\Architecture;

use Atelier\Diagram\Exception\InvalidArgumentException;

enum ArchitectureNodeKind: string
{
    case Person = 'person';
    case System = 'system';
    case Container = 'container';
    case Component = 'component';
    case Database = 'database';
    case Queue = 'queue';
    case External = 'external';

    public static function fromToken(string $token): self
    {
        return self::tryFrom($token) ?? throw new InvalidArgumentException(\sprintf('Unsupported architecture node kind "%s".', $token));
    }
}
