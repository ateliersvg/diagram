<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Parser\Support\ParserDiagnosticFormatter;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MermaidParser::class)]
#[CoversClass(ParserDiagnosticFormatter::class)]
final class MermaidParserDiagnosticFormatterTest extends TestCase
{
    public function testEveryRejectedCorpusSampleHasFormattedDiagnosticSnapshot(): void
    {
        $this->assertSame(
            self::readRejectedSampleNames(),
            array_keys(self::readFormattedDiagnosticSnapshots()),
        );
    }

    #[DataProvider('formattedDiagnostics')]
    public function testFormatsRejectedCorpusDiagnostics(string $sample, string $expected): void
    {
        try {
            (new MermaidParser())->parse(self::readRejectedSource($sample), self::readRejectedLimits($sample));
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame($expected, ParserDiagnosticFormatter::format($exception->getDiagnostic()));
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formattedDiagnostics(): iterable
    {
        foreach (self::readFormattedDiagnosticSnapshots() as $sample => $expected) {
            yield $sample => [$sample, $expected];
        }
    }

    private static function readRejectedSource(string $sample): string
    {
        $path = __DIR__.'/Fixtures/mermaid/rejected/'.$sample.'/source.mmd';
        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \LogicException(\sprintf('Cannot read rejected Mermaid fixture "%s".', $path));
        }

        return $contents;
    }

    private static function readRejectedLimits(string $sample): ?ParserInputLimits
    {
        /** @var array<string, array{maxSourceBytes?: int, maxLineBytes?: int}> $samples */
        $samples = require __DIR__.'/Fixtures/mermaid/rejected.php';
        $expected = $samples[$sample] ?? null;

        if (null === $expected || (!isset($expected['maxSourceBytes']) && !isset($expected['maxLineBytes']))) {
            return null;
        }

        return new ParserInputLimits(
            maxSourceBytes: $expected['maxSourceBytes'] ?? ParserInputLimits::DEFAULT_MAX_SOURCE_BYTES,
            maxLineBytes: $expected['maxLineBytes'] ?? ParserInputLimits::DEFAULT_MAX_LINE_BYTES,
        );
    }

    /**
     * @return list<string>
     */
    private static function readRejectedSampleNames(): array
    {
        /** @var array<string, array{code: string}> $samples */
        $samples = require __DIR__.'/Fixtures/mermaid/rejected.php';

        return array_keys($samples);
    }

    /**
     * @return array<string, string>
     */
    private static function readFormattedDiagnosticSnapshots(): array
    {
        $path = __DIR__.'/Fixtures/mermaid/rejected-diagnostics.txt';
        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \LogicException(\sprintf('Cannot read Mermaid diagnostic snapshot "%s".', $path));
        }

        $snapshots = [];
        $currentSample = null;
        $currentLines = [];

        foreach (explode("\n", rtrim($contents, "\n")) as $line) {
            if (str_starts_with($line, '# ')) {
                if (null !== $currentSample) {
                    $snapshots[$currentSample] = rtrim(implode("\n", $currentLines), "\n");
                }

                $currentSample = substr($line, 2);
                $currentLines = [];

                continue;
            }

            if ('' === $line && [] === $currentLines) {
                continue;
            }

            $currentLines[] = $line;
        }

        if (null !== $currentSample) {
            $snapshots[$currentSample] = rtrim(implode("\n", $currentLines), "\n");
        }

        return $snapshots;
    }
}
