<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\QuotedAttributeList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotedAttributeList::class)]
#[CoversClass(ParseErrors::class)]
final class QuotedAttributeListTest extends TestCase
{
    private const string INVALID = 'Invalid commit attribute, expected id: "..." or tag: "..."';

    public function testConstructorIsPrivateStaticOnlyHelper(): void
    {
        $class = new \ReflectionClass(QuotedAttributeList::class);
        $constructor = $class->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());

        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $this->assertInstanceOf(QuotedAttributeList::class, $instance);
    }

    public function testParsesQuotedAttributesInSourceOrder(): void
    {
        $attributes = QuotedAttributeList::parse(
            new Line(3, 'commit tag: "v1" id: "a1"'),
            'tag: "v1" id: "a1"',
            ['id', 'tag'],
            self::INVALID,
            'Duplicate commit attribute',
        );

        $this->assertSame(['tag' => 'v1', 'id' => 'a1'], $attributes);
    }

    public function testParsesEmptyQuotedValue(): void
    {
        $attributes = QuotedAttributeList::parse(
            new Line(3, 'commit id: ""'),
            'id: ""',
            ['id', 'tag'],
            self::INVALID,
            'Duplicate commit attribute',
        );

        $this->assertSame(['id' => ''], $attributes);
    }

    public function testRejectsUnknownAttributeName(): void
    {
        try {
            QuotedAttributeList::parse(new Line(3, 'commit type: "NORMAL"'), 'type: "NORMAL"', ['id', 'tag'], self::INVALID, 'Duplicate commit attribute');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Invalid commit attribute, expected id: "..." or tag: "..." at line 3: "commit type: "NORMAL""', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsNameStartingWithNonNameCharacter(): void
    {
        try {
            QuotedAttributeList::parse(new Line(3, 'commit 1: "x"'), '1: "x"', ['id', 'tag'], self::INVALID, 'Duplicate commit attribute');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Invalid commit attribute', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsValueWithoutOpeningQuote(): void
    {
        try {
            QuotedAttributeList::parse(new Line(3, 'commit id: x'), 'id: x', ['id', 'tag'], self::INVALID, 'Duplicate commit attribute');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Invalid commit attribute', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsTrailingCharactersAfterClosingQuote(): void
    {
        try {
            QuotedAttributeList::parse(new Line(3, 'commit id: "a1"x'), 'id: "a1"x', ['id', 'tag'], self::INVALID, 'Duplicate commit attribute');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Invalid commit attribute', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
        }
    }

    public function testRejectsUnclosedQuotedValue(): void
    {
        try {
            QuotedAttributeList::parse(new Line(4, 'commit id: "a1'), 'id: "a1', ['id', 'tag'], self::INVALID, 'Duplicate commit attribute');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Invalid commit attribute, expected id: "..." or tag: "..." at line 4: "commit id: "a1"', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('commit id: "a1', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testRejectsDuplicateAttributeName(): void
    {
        try {
            QuotedAttributeList::parse(new Line(5, 'commit id: "a1" id: "a2"'), 'id: "a1" id: "a2"', ['id', 'tag'], self::INVALID, 'Duplicate commit attribute');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate commit attribute "id" at line 5: "commit id: "a1" id: "a2""', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
            $this->assertSame(5, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(5, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('commit id: "a1" id: "a2"', $exception->getDiagnostic()->source?->content);
        }
    }
}
