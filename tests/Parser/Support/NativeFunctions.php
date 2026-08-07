<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

/**
 * Toggle that hides selected native functions from the namespaced
 * function_exists() override.
 *
 * It lets the test suite exercise the byte-oriented fallback branches in the
 * parser support classes (used when the mbstring extension is unavailable)
 * without disabling the extension globally.
 */
final class NativeFunctions
{
    /**
     * @var array<string, true>
     */
    private static array $hidden = [];

    public static function hide(string ...$names): void
    {
        foreach ($names as $name) {
            self::$hidden[$name] = true;
        }
    }

    public static function reset(): void
    {
        self::$hidden = [];
    }

    public static function isHidden(string $name): bool
    {
        return isset(self::$hidden[$name]);
    }
}
