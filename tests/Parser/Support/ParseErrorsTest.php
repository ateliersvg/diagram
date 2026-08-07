<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\ParserDiagnosticCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/native_function_overrides.php';

#[CoversClass(ParseErrors::class)]
final class ParseErrorsTest extends TestCase
{
    protected function tearDown(): void
    {
        NativeFunctions::reset();
    }

    public function testConstructorIsPrivateStaticOnlyHelper(): void
    {
        $class = new \ReflectionClass(ParseErrors::class);
        $constructor = $class->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());

        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $this->assertInstanceOf(ParseErrors::class, $instance);
    }

    public function testExpectedHeaderCarriesCode(): void
    {
        $exception = ParseErrors::expectedHeader(new Line(2, 'graph TD'), 'flowchart');

        $this->assertSame('Expected flowchart header at line 2: "graph TD"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::ExpectedHeader->value, $exception->getDiagnostic()->code);
    }

    public function testMissingHeaderReportsLineOne(): void
    {
        $exception = ParseErrors::missingHeader('sequence');

        $this->assertSame('Missing sequence header at line 1: ""', $exception->getMessage());
        $this->assertSame(1, $exception->getLineNumber());
        $this->assertSame(ParserDiagnosticCode::MissingHeader->value, $exception->getDiagnostic()->code);
    }

    public function testEmptyInputDescribesExpectedHeader(): void
    {
        $exception = ParseErrors::emptyInput('state');

        $this->assertSame('Expected state header, got empty input at line 1: ""', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::EmptyInput->value, $exception->getDiagnostic()->code);
        $this->assertSame('', $exception->getDiagnostic()->source?->content);
    }

    public function testSourceTooLargeReportsByteCounts(): void
    {
        $exception = ParseErrors::sourceTooLarge(2048, 1024);

        $this->assertSame('Parser source exceeds maximum size of 1024 bytes, got 2048 bytes at line 1: ""', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::SourceTooLarge->value, $exception->getDiagnostic()->code);
    }

    public function testLineTooLongTruncatesSourcePreview(): void
    {
        $exception = ParseErrors::lineTooLong(7, str_repeat('A', 200), 16);

        $this->assertStringStartsWith('Parser source line exceeds maximum length of 16 bytes at line 7:', $exception->getMessage());
        $this->assertSame(7, $exception->getLineNumber());
        $this->assertSame(ParserDiagnosticCode::LineTooLong->value, $exception->getDiagnostic()->code);
        $source = $exception->getDiagnostic()->source;
        $this->assertNotNull($source);
        $this->assertStringEndsWith('...', $source->content);
    }

    public function testUnknownHeaderListsExpected(): void
    {
        $exception = ParseErrors::unknownHeader(new Line(1, 'foo'), 'flowchart');

        $this->assertSame('Unknown diagram header, expected flowchart at line 1: "foo"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::UnknownHeader->value, $exception->getDiagnostic()->code);
    }

    public function testUnknownDiagramHeader(): void
    {
        $exception = ParseErrors::unknownDiagramHeader(new Line(1, 'foo'));

        $this->assertSame('Unknown diagram header at line 1: "foo"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::UnknownHeader->value, $exception->getDiagnostic()->code);
    }

    public function testCannotDetectDiagramType(): void
    {
        $exception = ParseErrors::cannotDetectDiagramType();

        $this->assertSame('Cannot detect the diagram type of empty input at line 1: ""', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::EmptyInput->value, $exception->getDiagnostic()->code);
    }

    public function testUnsupportedSyntax(): void
    {
        $exception = ParseErrors::unsupported(new Line(4, '<<weird>>'), 'flowchart');

        $this->assertSame('Unsupported flowchart syntax at line 4: "<<weird>>"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::UnsupportedSyntax->value, $exception->getDiagnostic()->code);
    }

    public function testSyntaxUsesProvidedCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('boom');
        $exception = ParseErrors::syntax(new Line(5, 'A == B'), 'Invalid edge', ParserDiagnosticCode::InvalidEnum, $previous);

        $this->assertSame('Invalid edge at line 5: "A == B"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::InvalidEnum->value, $exception->getDiagnostic()->code);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testSourceSyntaxUsesRawSourceLine(): void
    {
        $previous = new \RuntimeException('boom');
        $exception = ParseErrors::sourceSyntax(new SourceLine(6, '    indented'), 'Bad indentation', ParserDiagnosticCode::IndentationStep, $previous);

        $this->assertSame('Bad indentation at line 6: "    indented"', $exception->getMessage());
        $this->assertSame(6, $exception->getLineNumber());
        $this->assertSame(ParserDiagnosticCode::IndentationStep->value, $exception->getDiagnostic()->code);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testEmptyLabel(): void
    {
        $exception = ParseErrors::emptyLabel(new Line(3, 'A --> |  |'), 'Edge');

        $this->assertSame('Edge label must not be empty at line 3: "A --> |  |"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::EmptyLabel->value, $exception->getDiagnostic()->code);
    }

    public function testWrapTrimsTrailingDotAndKeepsPrevious(): void
    {
        $previous = new \RuntimeException('Block already exists.');
        $exception = ParseErrors::wrap(new Line(8, 'block A'), $previous);

        $this->assertSame('Block already exists at line 8: "block A"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::SemanticError->value, $exception->getDiagnostic()->code);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testUnexpectedBlockEnd(): void
    {
        $exception = ParseErrors::unexpectedBlockEnd(new Line(9, 'end'), 'flowchart');

        $this->assertSame('Unexpected flowchart block end at line 9: "end"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::UnexpectedBlockEnd->value, $exception->getDiagnostic()->code);
    }

    public function testExpectedOpenBlock(): void
    {
        $exception = ParseErrors::expectedOpenBlock(new Line(9, 'else'), 'sequence', 'alt');

        $this->assertSame('Expected open sequence alt block at line 9: "else"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::ExpectedOpenBlock->value, $exception->getDiagnostic()->code);
    }

    public function testExpectedBlockKind(): void
    {
        $exception = ParseErrors::expectedBlockKind(new Line(9, 'else'), 'sequence', 'alt', 'loop');

        $this->assertSame('Expected sequence alt block, got loop block at line 9: "else"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::ExpectedBlockKind->value, $exception->getDiagnostic()->code);
    }

    public function testUnclosedBlockDefaultsEndLineToOpeningLine(): void
    {
        $exception = ParseErrors::unclosedBlock(new Line(3, 'subgraph auth'), 'flowchart block');

        $this->assertSame('Unclosed flowchart block at line 3: "subgraph auth"', $exception->getMessage());
        $this->assertSame(ParserDiagnosticCode::UnclosedBlock->value, $exception->getDiagnostic()->code);
        $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
        $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        $this->assertSame(\strlen('subgraph auth') + 1, $exception->getDiagnostic()->span->endColumn);
    }

    public function testUnclosedBlockUsesExplicitEndLine(): void
    {
        $exception = ParseErrors::unclosedBlock(new Line(3, 'subgraph auth'), 'flowchart block', new Line(7, 'A --> B'));

        $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
        $this->assertSame(7, $exception->getDiagnostic()->span->endLine);
        $this->assertSame(\strlen('A --> B') + 1, $exception->getDiagnostic()->span->endColumn);
    }

    public function testUnclosedBlockColumnFallsBackToByteLengthWithoutMbstring(): void
    {
        NativeFunctions::hide('mb_strlen');

        $exception = ParseErrors::unclosedBlock(new Line(2, 'loop x'), 'sequence block', new Line(5, 'café'));

        // strlen('café') is 5 bytes (é is two bytes), so end column is 6.
        $this->assertSame(\strlen('café') + 1, $exception->getDiagnostic()->span->endColumn);
    }
}
