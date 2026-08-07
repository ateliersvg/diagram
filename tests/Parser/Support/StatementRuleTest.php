<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\StatementMatch;
use Atelier\Diagram\Parser\Support\StatementMatcher;
use Atelier\Diagram\Parser\Support\StatementRule;
use Atelier\Diagram\Parser\Support\StatementRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementRule::class)]
#[CoversClass(StatementMatch::class)]
#[CoversClass(StatementMatcher::class)]
#[CoversClass(StatementRules::class)]
final class StatementRuleTest extends TestCase
{
    public function testMatchesNamedRule(): void
    {
        $rule = StatementRule::regex('title', '/^title\s+(.+)$/');
        $match = $rule->match(new Line(3, 'title Checkout'));

        $this->assertInstanceOf(StatementMatch::class, $match);
        $this->assertSame('title', $match->rule->name);
        $this->assertSame('title Checkout', $match->get(0));
        $this->assertSame('Checkout', $match->get(1));
        $this->assertNull($match->optional(2));
    }

    public function testStatementMatcherCanMatchNamedRule(): void
    {
        $matcher = new StatementMatcher(new Line(4, 'section Build'));
        $match = $matcher->matchRule(StatementRule::regex('section', '/^section\s+(.+)$/'));

        $this->assertSame('Build', $match?->get(1));
    }

    public function testStatementMatcherFactoryCreatesMatcher(): void
    {
        $match = StatementMatcher::for(new Line(4, 'section Build'))
            ->matchRule(StatementRule::regex('section', '/^section\s+(.+)$/'));

        $this->assertSame('Build', $match?->get(1));
    }

    public function testMissingCaptureThrowsWithRuleName(): void
    {
        $match = StatementRule::regex('title', '/^title\s+(.+)$/')->match(new Line(3, 'title Checkout'));
        self::assertNotNull($match);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statement "title" has no capture 2.');

        $match->get(2);
    }

    public function testRejectsEmptyRuleName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statement rule name must be non-empty.');

        StatementRule::regex('', '/^title\s+(.+)$/');
    }

    public function testRejectsEmptyPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statement rule pattern must be non-empty.');

        StatementRule::regex('valid', '   ');
    }

    public function testRejectsInvalidRegexPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statement rule "broken" pattern is invalid.');

        StatementRule::regex('broken', '/unterminated');
    }

    public function testCatalogReturnsSameRuleForSameNameAndPattern(): void
    {
        $first = StatementRules::get('test.catalog.title', '/^title\s+(.+)$/');
        $second = StatementRules::get('test.catalog.title', '/^title\s+(.+)$/');

        $this->assertSame($first, $second);
        $this->assertSame($first, StatementRules::all()['test.catalog.title']);
    }

    public function testCatalogRejectsDuplicateNameWithDifferentPattern(): void
    {
        StatementRules::get('test.catalog.conflict', '/^title\s+(.+)$/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statement rule "test.catalog.conflict" is already registered with a different pattern.');

        StatementRules::get('test.catalog.conflict', '/^section\s+(.+)$/');
    }
}
