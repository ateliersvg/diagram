<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParserValues::class)]
#[CoversClass(ParseErrors::class)]
final class ParserValuesTest extends TestCase
{
    public function testConstructorIsPrivateStaticOnlyHelper(): void
    {
        $class = new \ReflectionClass(ParserValues::class);
        $constructor = $class->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());

        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $this->assertInstanceOf(ParserValues::class, $instance);
    }

    public function testNonEmptyTrimsValue(): void
    {
        $this->assertSame('Title', ParserValues::nonEmpty(new Line(2, 'title Title'), ' Title ', 'Title'));
    }

    public function testNonEmptyLabelTrimsPresentValue(): void
    {
        $this->assertSame('Edge', ParserValues::nonEmptyLabel(new Line(4, 'A -> B : Edge'), ' Edge ', 'Edge'));
    }

    public function testOptionalNonEmptyLabelTrimsPresentValue(): void
    {
        $this->assertSame('Block', ParserValues::optionalNonEmptyLabel(new Line(1, 'block A'), ' Block ', 'Block'));
    }

    public function testEnumTokenAcceptsKnownToken(): void
    {
        $this->assertSame('high', ParserValues::enumToken(new Line(7, 'risk: high'), 'high', ['low', 'medium', 'high'], 'Risk'));
    }

    public function testNonEmptyThrowsWithLineContext(): void
    {
        try {
            ParserValues::nonEmpty(new Line(3, 'title   '), '   ', 'Title');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Title must not be empty at line 3: "title   "', $exception->getMessage());
            $this->assertSame('parser.empty_value', $exception->getDiagnostic()->code);
        }
    }

    public function testNonEmptyLabelUsesLabelDiagnostic(): void
    {
        try {
            ParserValues::nonEmptyLabel(new Line(4, 'A -> B : '), '', 'Edge');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Edge label must not be empty at line 4: "A -> B : "', $exception->getMessage());
            $this->assertSame('parser.empty_label', $exception->getDiagnostic()->code);
        }
    }

    public function testOptionalNonEmptyLabelAllowsNull(): void
    {
        $this->assertNull(ParserValues::optionalNonEmptyLabel(new Line(1, 'block A'), null, 'Block'));
    }

    public function testOptionalNonEmptyTrimsPresentValue(): void
    {
        $this->assertSame('Title', ParserValues::optionalNonEmpty(new Line(1, 'title Title'), ' Title ', 'Title'));
        $this->assertNull(ParserValues::optionalNonEmpty(new Line(1, 'title'), null, 'Title'));
    }

    public function testCommaSeparatedTrimsItems(): void
    {
        $this->assertSame(['Customer', 'PSP'], ParserValues::commaSeparated(new Line(5, 'actors'), ' Customer, PSP ', 'Actors'));
    }

    public function testCommaSeparatedRejectsEmptyItems(): void
    {
        try {
            ParserValues::commaSeparated(new Line(5, 'Customer,'), 'Customer,', 'Actors');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Actors must not contain empty items at line 5', $exception->getMessage());
            $this->assertSame('parser.empty_list_item', $exception->getDiagnostic()->code);
        }
    }

    public function testEnumTokenRejectsUnknownToken(): void
    {
        try {
            ParserValues::enumToken(new Line(7, 'risk: urgent'), 'urgent', ['low', 'medium', 'high'], 'Risk');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Risk must be one of: low, medium, high at line 7', $exception->getMessage());
            $this->assertSame('parser.invalid_enum', $exception->getDiagnostic()->code);
        }
    }
}
