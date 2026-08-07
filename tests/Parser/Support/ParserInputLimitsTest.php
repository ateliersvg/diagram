<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParserInputLimits::class)]
final class ParserInputLimitsTest extends TestCase
{
    public function testDefaultsExposeSafetyBounds(): void
    {
        $limits = ParserInputLimits::default();

        $this->assertSame(ParserInputLimits::DEFAULT_MAX_SOURCE_BYTES, $limits->maxSourceBytes);
        $this->assertSame(ParserInputLimits::DEFAULT_MAX_LINE_BYTES, $limits->maxLineBytes);
    }

    public function testRejectsNonPositiveSourceLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parser maxSourceBytes must be positive.');

        new ParserInputLimits(maxSourceBytes: 0);
    }

    public function testRejectsNonPositiveLineLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parser maxLineBytes must be positive.');

        new ParserInputLimits(maxLineBytes: 0);
    }

    public function testAllowsCustomPositiveLimits(): void
    {
        $limits = new ParserInputLimits(maxSourceBytes: 42, maxLineBytes: 84);

        $this->assertSame(42, $limits->maxSourceBytes);
        $this->assertSame(84, $limits->maxLineBytes);
    }
}
