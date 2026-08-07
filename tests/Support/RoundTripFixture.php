<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Support;

use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Renderer\Markdown\MarkdownRenderer;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\TestCase;

/**
 * Test helper for parser/serializer/layout equivalence.
 */
final class RoundTripFixture
{
    private function __construct()
    {
    }

    public static function assertCanonicalMermaidSourceRoundTrip(TestCase $test, string $source): object
    {
        $markdown = new MarkdownRenderer();
        $parser = new MermaidParser();

        $model = $parser->parse($source);
        $canonical = $markdown->renderMermaid($model);
        $reparsed = $parser->parse($canonical);

        $test->assertSame($canonical, $markdown->renderMermaid($reparsed));

        return $reparsed;
    }

    public static function assertMarkdownFencedMermaidSourceRoundTrip(TestCase $test, string $source): object
    {
        $markdown = new MarkdownRenderer();
        $parser = new MermaidParser();

        $model = $parser->parse($source);
        $fenced = $markdown->render($model);
        $canonical = $markdown->renderMermaid($model);

        $test->assertSame("```mermaid\n".$canonical.'```'."\n", $fenced);
        $test->assertSame($fenced, $markdown->render($parser->parse($canonical)));

        return $model;
    }

    /**
     * @template T of object
     *
     * @param T                 $model
     * @param callable(T):Scene $layout
     */
    public static function assertMermaidRoundTrip(TestCase $test, object $model, callable $layout): void
    {
        $markdown = new MarkdownRenderer();
        $parser = new MermaidParser();
        $svg = new SvgRenderer();
        $theme = Theme::default();

        $mermaid = $markdown->renderMermaid($parser->parse($markdown->renderMermaid($model)));
        $test->assertSame($mermaid, $markdown->renderMermaid($parser->parse($mermaid)));
        $test->assertSame(
            $svg->render($layout($model)),
            $svg->render($layout($parser->parse($markdown->renderMermaid($model)))),
        );
    }
}
