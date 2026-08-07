<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Support;

use Atelier\Diagram\Support\Decimal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Decimal::class)]
final class DecimalTest extends TestCase
{
    /**
     * PHP 8.4 removed the pre-rounding that round() applied to ~15 significant
     * digits, so round(97.57499999999998, 2) returns 97.58 on PHP 8.3 and 97.57
     * on PHP 8.4+. Coordinates that land within ~1e-13 of a rounding midpoint
     * therefore rendered differently across supported PHP versions.
     *
     * These expectations are absolute: they must hold on every version the
     * package supports.
     */
    #[DataProvider('provideCoordinates')]
    public function testFormatIsIndependentOfThePhpVersion(float $value, string $expected): void
    {
        $this->assertSame($expected, Decimal::format($value));
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function provideCoordinates(): iterable
    {
        yield 'below a midpoint by one ulp' => [97.57499999999998863132, '97.57'];
        yield 'above a midpoint by one ulp' => [97.57500000000001705303, '97.58'];
        yield 'another below-midpoint double' => [2.674999999999999822, '2.67'];
        yield 'integer value drops the decimals' => [5.0, '5'];
        yield 'single decimal keeps one digit' => [2.5, '2.5'];
        yield 'two decimals are kept' => [97.57, '97.57'];
        yield 'zero' => [0.0, '0'];
        yield 'negative below a midpoint' => [-1.00499999999999989342, '-1'];
        yield 'third decimal is dropped' => [12.3456, '12.35'];

        // Exact ties round to even, the IEEE 754 default that sprintf applies.
        // round() rounds them away from zero instead, so these are the only
        // values whose rendered output changed when the formatter was swapped.
        yield 'exact tie rounds down to an even digit' => [0.125, '0.12'];
        yield 'exact tie rounds down to an even digit, again' => [1.125, '1.12'];
        yield 'exact tie rounds up to an even digit' => [2.375, '2.38'];
    }

    public function testRoundReturnsTheSameValueAsFormat(): void
    {
        $this->assertSame(97.57, Decimal::round(97.57499999999998863132));
        $this->assertSame(97.58, Decimal::round(97.57500000000001705303));
        $this->assertSame(5.0, Decimal::round(5.0));
    }
}
