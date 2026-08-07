<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Renderer\Markdown\MarkdownRenderer;
use Atelier\Diagram\Tests\Support\MermaidCorpusFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MermaidParser::class)]
#[CoversClass(MarkdownRenderer::class)]
final class MermaidParserCorpusTest extends TestCase
{
    /**
     * @param class-string $expectedModel
     */
    #[DataProvider('acceptedSamples')]
    public function testAcceptedSamplesRenderToCanonicalMermaid(string $source, string $expectedCanonical, string $expectedModel): void
    {
        MermaidCorpusFixture::assertAccepted($this, $source, $expectedCanonical, $expectedModel);
    }

    #[DataProvider('rejectedSamples')]
    public function testRejectedSamplesExposeStableDiagnosticCodes(string $source, string $expectedCode, ?int $expectedStartLine = null, ?int $expectedEndLine = null, ?string $expectedSource = null, ?ParserInputLimits $limits = null): void
    {
        MermaidCorpusFixture::assertRejected($this, $source, $expectedCode, $expectedStartLine, $expectedEndLine, $expectedSource, $limits);
    }

    /**
     * @return iterable<string, array{string, string, class-string}>
     */
    public static function acceptedSamples(): iterable
    {
        yield from MermaidCorpusFixture::acceptedSamples();
    }

    /**
     * @return iterable<string, array{string, string, ?int, ?int, ?string, ?ParserInputLimits}>
     */
    public static function rejectedSamples(): iterable
    {
        yield from MermaidCorpusFixture::rejectedSamples();
    }
}
