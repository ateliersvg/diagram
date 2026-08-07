<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementMatcher::class)]
final class StatementMatcherTest extends TestCase
{
    public function testMatchReturnsCapturedGroups(): void
    {
        $matcher = StatementMatcher::for(new Line(2, 'A --> B'));

        $this->assertSame(['A --> B', 'A', 'B'], $matcher->match('/^(\w+)\s*-->\s*(\w+)$/'));
    }

    public function testMatchReturnsNullWhenPatternDoesNotMatch(): void
    {
        $matcher = StatementMatcher::for(new Line(2, 'A --> B'));

        $this->assertNull($matcher->match('/^skip:/'));
    }

    public function testMatchRuleDelegatesToRule(): void
    {
        $matcher = StatementMatcher::for(new Line(4, 'state Foo'));
        $rule = StatementRule::regex('state', '/^state\s+(\w+)$/');

        $match = $matcher->matchRule($rule);

        $this->assertNotNull($match);
        $this->assertSame('Foo', $match->get(1));
    }

    public function testMatchRuleReturnsNullWhenRuleDoesNotMatch(): void
    {
        $matcher = StatementMatcher::for(new Line(4, 'note Foo'));
        $rule = StatementRule::regex('state', '/^state\s+(\w+)$/');

        $this->assertNull($matcher->matchRule($rule));
    }
}
