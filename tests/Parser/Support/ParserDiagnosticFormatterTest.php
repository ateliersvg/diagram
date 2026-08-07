<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\ParserDiagnostic;
use Atelier\Diagram\Parser\Support\ParserDiagnosticFormatter;
use Atelier\Diagram\Parser\Support\SourceExcerpt;
use Atelier\Diagram\Parser\Support\SourceSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/native_function_overrides.php';

#[CoversClass(ParserDiagnosticFormatter::class)]
#[CoversClass(ParserDiagnostic::class)]
#[CoversClass(SourceExcerpt::class)]
#[CoversClass(SourceSpan::class)]
final class ParserDiagnosticFormatterTest extends TestCase
{
    protected function tearDown(): void
    {
        NativeFunctions::reset();
    }

    public function testConstructorIsPrivateStaticOnlyHelper(): void
    {
        $class = new \ReflectionClass(ParserDiagnosticFormatter::class);
        $constructor = $class->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());

        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $this->assertInstanceOf(ParserDiagnosticFormatter::class, $instance);
    }

    public function testMarkerFallsBackToSingleCaretWithoutSource(): void
    {
        $diagnostic = ParserDiagnostic::error('Cannot detect diagram type', SourceSpan::forLine(1, ''), 'parser.empty_input');
        $this->assertNull($diagnostic->source);

        $marker = new \ReflectionMethod(ParserDiagnosticFormatter::class, 'marker');

        $this->assertSame('^', $marker->invoke(null, $diagnostic));
    }

    public function testFormatsSingleLineMarkerWithoutMbstring(): void
    {
        NativeFunctions::hide('mb_substr', 'mb_strlen');

        $diagnostic = ParserDiagnostic::error(
            'Invalid edge',
            new SourceSpan(4, 3, 4, 6),
            'parser.syntax_error',
            SourceExcerpt::fromSource(4, 'A --> B'),
        );

        $this->assertSame(
            <<<TEXT
            Invalid edge [parser.syntax_error] at line 4
            4 | A --> B
              |   ^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsMultiLineMarkerWithoutMbstring(): void
    {
        NativeFunctions::hide('mb_substr', 'mb_strlen');

        $diagnostic = ParserDiagnostic::error(
            'Unclosed subgraph',
            new SourceSpan(3, 1, 7, 9),
            'parser.unclosed_block',
            SourceExcerpt::fromSource(3, 'subgraph auth'),
        );

        $this->assertSame(
            <<<TEXT
            Unclosed subgraph [parser.unclosed_block] at line 3
            3 | subgraph auth
              | ^^^^^^^^^^^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsDiagnosticWithSourceFrame(): void
    {
        $diagnostic = ParserDiagnostic::forLine('Unsupported flowchart syntax', new Line(9, 'bad'), 'parser.unsupported_syntax');

        $this->assertSame(
            <<<TEXT
            Unsupported flowchart syntax [parser.unsupported_syntax] at line 9
            9 | bad
              | ^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsDiagnosticWithTwoDigitLineNumber(): void
    {
        $diagnostic = ParserDiagnostic::forLine('Unsupported sequence syntax', new Line(12, 'alt broken'), 'parser.unsupported_syntax');

        $this->assertSame(
            <<<TEXT
            Unsupported sequence syntax [parser.unsupported_syntax] at line 12
            12 | alt broken
               | ^^^^^^^^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsDiagnosticWithoutSourceFrame(): void
    {
        $diagnostic = ParserDiagnostic::error('Cannot detect diagram type', SourceSpan::forLine(1, ''), 'parser.empty_input');

        $this->assertSame(
            'Cannot detect diagram type [parser.empty_input] at line 1',
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsEmptySourceFrameWithoutTrailingWhitespace(): void
    {
        $diagnostic = ParserDiagnostic::error(
            'Parser source exceeds maximum size of 16 bytes, got 29 bytes',
            SourceSpan::forLine(1, ''),
            'parser.source_too_large',
            SourceExcerpt::fromSource(1, ''),
        );

        $this->assertSame(
            <<<TEXT
            Parser source exceeds maximum size of 16 bytes, got 29 bytes [parser.source_too_large] at line 1
            1 |
              | ^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsMarkerFromSpanColumn(): void
    {
        $diagnostic = ParserDiagnostic::error(
            'Invalid edge',
            new SourceSpan(4, 3, 4, 6),
            'parser.syntax_error',
            SourceExcerpt::fromSource(4, 'A --> B'),
        );

        $this->assertSame(
            <<<TEXT
            Invalid edge [parser.syntax_error] at line 4
            4 | A --> B
              |   ^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsMarkerFromSpanColumnWithMultibytePrefix(): void
    {
        $diagnostic = ParserDiagnostic::error(
            'Invalid edge',
            new SourceSpan(4, 5, 4, 8),
            'parser.syntax_error',
            SourceExcerpt::fromSource(4, 'ééA --> B'),
        );

        $this->assertSame(
            <<<TEXT
            Invalid edge [parser.syntax_error] at line 4
            4 | ééA --> B
              |     ^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsMultiLineDiagnosticOnExcerptLine(): void
    {
        $diagnostic = ParserDiagnostic::error(
            'Unclosed subgraph',
            new SourceSpan(3, 1, 7, 9),
            'parser.unclosed_block',
            SourceExcerpt::fromSource(3, 'subgraph auth'),
        );

        $this->assertSame(
            <<<TEXT
            Unclosed subgraph [parser.unclosed_block] at line 3
            3 | subgraph auth
              | ^^^^^^^^^^^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsMultilineDiagnosticWithMultibyteExcerpt(): void
    {
        $diagnostic = ParserDiagnostic::error(
            'Unclosed subgraph',
            new SourceSpan(3, 1, 7, 10),
            'parser.unclosed_block',
            SourceExcerpt::fromSource(3, 'é😀subgraph auth'),
        );

        $this->assertSame(
            <<<TEXT
            Unclosed subgraph [parser.unclosed_block] at line 3
            3 | é😀subgraph auth
              | ^^^^^^^^^^^^^^^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }

    public function testFormatsDiagnosticOnLaterExcerptLineWithoutMarkerForDifferentStartLine(): void
    {
        $diagnostic = ParserDiagnostic::error(
            'Invalid edge',
            new SourceSpan(4, 1, 6, 5),
            'parser.unclosed_block',
            SourceExcerpt::fromSource(6, 'end'),
        );

        $this->assertSame(
            <<<TEXT
            Invalid edge [parser.unclosed_block] at line 4
            6 | end
              | ^
            TEXT,
            ParserDiagnosticFormatter::format($diagnostic),
        );
    }
}
