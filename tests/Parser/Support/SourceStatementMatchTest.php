<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\SourceLine;
use Atelier\Diagram\Parser\Support\SourceStatementMatch;
use Atelier\Diagram\Parser\Support\SourceStatementRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceStatementMatch::class)]
final class SourceStatementMatchTest extends TestCase
{
    public function testGetReturnsCaptureByIndex(): void
    {
        $match = $this->match();

        $this->assertSame('A --> B', $match->get(0));
        $this->assertSame('A', $match->get(1));
        $this->assertSame('B', $match->get(2));
    }

    public function testGetThrowsForUnknownCapture(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source statement "edge" has no capture 5.');

        $this->match()->get(5);
    }

    public function testOptionalReturnsCaptureWhenPresent(): void
    {
        $this->assertSame('B', $this->match()->optional(2));
    }

    public function testOptionalReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->match()->optional(5));
    }

    public function testCapturesReturnsAllValues(): void
    {
        $this->assertSame(['A --> B', 'A', 'B'], $this->match()->captures());
    }

    public function testExposesRuleAndLine(): void
    {
        $match = $this->match();

        $this->assertSame('edge', $match->rule->name);
        $this->assertSame(3, $match->line->number);
    }

    private function match(): SourceStatementMatch
    {
        $rule = SourceStatementRule::regex('edge', '/^(\w+)\s*-->\s*(\w+)$/');

        return new SourceStatementMatch($rule, new SourceLine(3, 'A --> B'), ['A --> B', 'A', 'B']);
    }
}
