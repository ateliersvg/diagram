<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests;

/**
 * Loads a builder script from examples/ as a test fixture.
 *
 * Several snapshot and round-trip tests reuse the reference diagrams defined
 * in examples/ so that the shipped example and the asserted model cannot drift
 * apart. That puts the fixture outside tests/, in a directory that is neither
 * autoloaded nor part of the published package.
 *
 * The proper fix is to move those builders into tests/ and have the example
 * scripts consume them, rather than the reverse. Until then this trait keeps
 * the dependency in one place: when examples/ is absent from the checkout the
 * tests skip with an explicit reason instead of fataling on a missing require.
 */
trait ExampleFixtures
{
    /**
     * @param string $name file name inside examples/, e.g. 'state-machine.php'
     */
    protected static function loadExample(string $name): void
    {
        $path = \dirname(__DIR__).'/examples/'.$name;

        if (!is_file($path)) {
            self::markTestSkipped(\sprintf('examples/%s is not part of this checkout.', $name));
        }

        require_once $path;
    }
}
