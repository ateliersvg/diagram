<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderParser::class)]
#[CoversClass(HeaderMatch::class)]
#[CoversClass(ParseErrors::class)]
final class HeaderParserTest extends TestCase
{
    public function testConstructorIsPrivateStaticOnlyHelper(): void
    {
        $class = new \ReflectionClass(HeaderParser::class);
        $constructor = $class->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());

        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $this->assertInstanceOf(HeaderParser::class, $instance);
    }

    public function testFirstSignificantLineRejectsOversizedSource(): void
    {
        try {
            HeaderParser::firstSignificantLine('sequenceDiagram', new ParserInputLimits(maxSourceBytes: 4));
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Parser source exceeds maximum size of 4 bytes', $exception->getMessage());
            $this->assertSame('parser.source_too_large', $exception->getDiagnostic()->code);
        }
    }

    public function testFirstSignificantLineRejectsOverlongLine(): void
    {
        try {
            HeaderParser::firstSignificantLine("sequenceDiagram\n", new ParserInputLimits(maxSourceBytes: 1000, maxLineBytes: 4));
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertStringContainsString('Parser source line exceeds maximum length of 4 bytes', $exception->getMessage());
            $this->assertSame('parser.line_too_long', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getLineNumber());
        }
    }

    public function testKeywordExtractsTheFirstToken(): void
    {
        $this->assertSame('sequenceDiagram', HeaderParser::keyword('sequenceDiagram title'));
        $this->assertSame('timeline', HeaderParser::keyword("timeline\tlaunch"));
        $this->assertSame('', HeaderParser::keyword('  sequenceDiagram'));
    }

    public function testFirstSignificantLineSkipsBomBlankAndCommentLines(): void
    {
        $source = "\xEF\xBB\xBF%% generated\n\n  %% ignored\nsequenceDiagram\nA->>B: Hello\n";

        $header = HeaderParser::firstSignificantLine($source);

        $this->assertSame(4, $header->line->number);
        $this->assertSame('sequenceDiagram', $header->line->content);
        $this->assertSame(\strlen("\xEF\xBB\xBF%% generated\n\n  %% ignored\nsequenceDiagram\n"), $header->nextOffset);
        $this->assertSame("A->>B: Hello\n", substr($source, $header->nextOffset));
    }

    public function testFirstSignificantLineSkipsOtherSingleByteSeparators(): void
    {
        $source = "%% generated\r\r  %% ignored\vsequenceDiagram\fA->>B: Hello";

        $header = HeaderParser::firstSignificantLine($source);

        $this->assertSame(4, $header->line->number);
        $this->assertSame('sequenceDiagram', $header->line->content);
        $this->assertSame(\strpos($source, 'A->>B: Hello'), $header->nextOffset);
        $this->assertSame('A->>B: Hello', substr($source, $header->nextOffset));
    }

    public function testFirstSignificantLineRejectsEmptySources(): void
    {
        try {
            HeaderParser::firstSignificantLine("%% generated\n\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Cannot detect the diagram type of empty input at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.empty_input', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
        }
    }
}
