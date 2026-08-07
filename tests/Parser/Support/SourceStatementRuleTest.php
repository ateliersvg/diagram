<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\SourceLine;
use Atelier\Diagram\Parser\Support\SourceStatementMatch;
use Atelier\Diagram\Parser\Support\SourceStatementMatcher;
use Atelier\Diagram\Parser\Support\SourceStatementRule;
use Atelier\Diagram\Parser\Support\SourceStatementRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceStatementRule::class)]
#[CoversClass(SourceStatementMatch::class)]
#[CoversClass(SourceStatementMatcher::class)]
#[CoversClass(SourceStatementRules::class)]
final class SourceStatementRuleTest extends TestCase
{
    public function testMatchesTrimmedSourceLineAndKeepsRawLine(): void
    {
        $line = new SourceLine(5, '    todo [Todo]');
        $rule = SourceStatementRule::regex('kanban.column.test', '/^([A-Za-z_][A-Za-z0-9_-]*)\s+\[([^\]]+)\]$/');

        $match = $rule->match($line);

        $this->assertInstanceOf(SourceStatementMatch::class, $match);
        $this->assertSame($line, $match->line);
        $this->assertSame('todo [Todo]', $match->get(0));
        $this->assertSame('todo', $match->get(1));
        $this->assertSame('Todo', $match->get(2));
    }

    public function testSourceStatementMatcherCanMatchNamedRule(): void
    {
        $match = SourceStatementMatcher::for(new SourceLine(2, '  root((Atelier))'))
            ->matchRule(SourceStatementRule::regex('mindmap.root.test', '/^root\(\((.+)\)\)$/'));

        $this->assertSame('Atelier', $match?->get(1));
    }

    public function testMissingCaptureThrowsWithRuleName(): void
    {
        $match = SourceStatementRule::regex('kanban.title.test', '/^title\s+(.+)$/')->match(new SourceLine(3, '    title Board'));
        self::assertNotNull($match);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source statement "kanban.title.test" has no capture 2.');

        $match->get(2);
    }

    public function testRejectsEmptyRuleName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source statement rule name must be non-empty.');

        SourceStatementRule::regex('   ', '/^title\s+(.+)$/');
    }

    public function testRejectsEmptyPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source statement rule pattern must be non-empty.');

        SourceStatementRule::regex('valid', '   ');
    }

    public function testRejectsInvalidRegexPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source statement rule "broken" pattern is invalid.');

        SourceStatementRule::regex('broken', '/unterminated');
    }

    public function testCatalogReturnsSameRuleForSameNameAndPattern(): void
    {
        $first = SourceStatementRules::get('test.source.catalog.title', '/^title\s+(.+)$/');
        $second = SourceStatementRules::get('test.source.catalog.title', '/^title\s+(.+)$/');

        $this->assertSame($first, $second);
        $this->assertSame($first, SourceStatementRules::all()['test.source.catalog.title']);
    }

    public function testCatalogRejectsDuplicateNameWithDifferentPattern(): void
    {
        SourceStatementRules::get('test.source.catalog.conflict', '/^title\s+(.+)$/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source statement rule "test.source.catalog.conflict" is already registered with a different pattern.');

        SourceStatementRules::get('test.source.catalog.conflict', '/^section\s+(.+)$/');
    }
}
