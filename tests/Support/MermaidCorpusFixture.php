<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Renderer\Markdown\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class MermaidCorpusFixture
{
    private const string FIXTURE_DIR = __DIR__.'/../Parser/Fixtures/mermaid';

    private function __construct()
    {
    }

    /**
     * @return iterable<string, array{string, string, class-string}>
     */
    public static function acceptedSamples(): iterable
    {
        /** @var array<string, class-string> $samples */
        $samples = require self::FIXTURE_DIR.'/accepted.php';

        foreach ($samples as $name => $expectedModel) {
            yield $name => [
                self::read($name, 'accepted', 'source.mmd'),
                self::read($name, 'accepted', 'canonical.mmd'),
                $expectedModel,
            ];
        }
    }

    /**
     * @return iterable<string, array{string, string, ?int, ?int, ?string, ?ParserInputLimits}>
     */
    public static function rejectedSamples(): iterable
    {
        /** @var array<string, array{code: string, startLine?: int, endLine?: int, source?: string, maxSourceBytes?: int, maxLineBytes?: int}> $samples */
        $samples = require self::FIXTURE_DIR.'/rejected.php';

        foreach ($samples as $name => $expected) {
            yield $name => [
                self::read($name, 'rejected', 'source.mmd'),
                $expected['code'],
                $expected['startLine'] ?? null,
                $expected['endLine'] ?? null,
                $expected['source'] ?? null,
                self::limits($expected),
            ];
        }
    }

    /**
     * @param class-string $expectedModel
     */
    public static function assertAccepted(TestCase $test, string $source, string $expectedCanonical, string $expectedModel): void
    {
        $parser = new MermaidParser();
        $renderer = new MarkdownRenderer();

        $model = $parser->parse($source);

        $test->assertInstanceOf($expectedModel, $model);
        $test->assertSame($expectedCanonical, $renderer->renderMermaid($model));

        $reparsed = $parser->parse($expectedCanonical);
        $test->assertInstanceOf($expectedModel, $reparsed);
        $test->assertSame($expectedCanonical, $renderer->renderMermaid($reparsed));
    }

    public static function assertRejected(TestCase $test, string $source, string $expectedCode, ?int $expectedStartLine = null, ?int $expectedEndLine = null, ?string $expectedSource = null, ?ParserInputLimits $limits = null): void
    {
        try {
            (new MermaidParser())->parse($source, $limits);
            $test->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $test->assertSame($expectedCode, $exception->getDiagnostic()->code);
            $test->assertSame($exception->getLineNumber(), $exception->getDiagnostic()->lineNumber());
            if (null !== $expectedStartLine) {
                $test->assertSame($expectedStartLine, $exception->getDiagnostic()->span->startLine);
            }
            if (null !== $expectedEndLine) {
                $test->assertSame($expectedEndLine, $exception->getDiagnostic()->span->endLine);
            }
            if (null !== $expectedSource) {
                $test->assertSame($expectedSource, $exception->getDiagnostic()->source?->content);
            }
        }
    }

    /**
     * @param array{maxSourceBytes?: int, maxLineBytes?: int} $expected
     */
    private static function limits(array $expected): ?ParserInputLimits
    {
        if (!isset($expected['maxSourceBytes']) && !isset($expected['maxLineBytes'])) {
            return null;
        }

        return new ParserInputLimits(
            maxSourceBytes: $expected['maxSourceBytes'] ?? ParserInputLimits::DEFAULT_MAX_SOURCE_BYTES,
            maxLineBytes: $expected['maxLineBytes'] ?? ParserInputLimits::DEFAULT_MAX_LINE_BYTES,
        );
    }

    private static function read(string $sample, string $kind, string $file): string
    {
        $path = self::FIXTURE_DIR.'/'.$kind.'/'.$sample.'/'.$file;
        if (!is_file($path)) {
            throw new \LogicException(\sprintf('Missing Mermaid corpus fixture "%s".', $path));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \LogicException(\sprintf('Cannot read Mermaid corpus fixture "%s".', $path));
        }

        return $contents;
    }
}
