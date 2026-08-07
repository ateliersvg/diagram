<?php

declare(strict_types=1);

namespace Atelier\Diagram\C4;

use Atelier\Diagram\Exception\InvalidArgumentException;

enum C4ElementKind: string
{
    case Person = 'person';
    case PersonExternal = 'personExternal';
    case System = 'system';
    case SystemExternal = 'systemExternal';
    case Container = 'container';
    case ContainerExternal = 'containerExternal';
    case ContainerDatabase = 'containerDatabase';
    case Component = 'component';
    case ComponentExternal = 'componentExternal';
    case ComponentDatabase = 'componentDatabase';

    public static function fromMacro(string $macro): self
    {
        return match ($macro) {
            'Person' => self::Person,
            'Person_Ext' => self::PersonExternal,
            'System' => self::System,
            'System_Ext' => self::SystemExternal,
            'Container' => self::Container,
            'Container_Ext' => self::ContainerExternal,
            'ContainerDb' => self::ContainerDatabase,
            'Component' => self::Component,
            'Component_Ext' => self::ComponentExternal,
            'ComponentDb' => self::ComponentDatabase,
            default => throw new InvalidArgumentException(\sprintf('Unsupported C4 element macro "%s".', $macro)),
        };
    }

    public function macro(): string
    {
        return match ($this) {
            self::Person => 'Person',
            self::PersonExternal => 'Person_Ext',
            self::System => 'System',
            self::SystemExternal => 'System_Ext',
            self::Container => 'Container',
            self::ContainerExternal => 'Container_Ext',
            self::ContainerDatabase => 'ContainerDb',
            self::Component => 'Component',
            self::ComponentExternal => 'Component_Ext',
            self::ComponentDatabase => 'ComponentDb',
        };
    }
}
