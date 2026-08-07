<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Sequence;

use Atelier\Diagram\Layout\Sequence\SequenceLayoutEngine;
use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequenceLayoutEngine::class)]
final class SequenceLayoutEngineTest extends TestCase
{
    use ExampleFixtures;

    public function testRendersParticipantTracksMessagesAndSelfMessage(): void
    {
        self::loadExample('sequence-workflow.php');

        $svg = (new SvgRenderer())->render((new SequenceLayoutEngine())->layout(buildSequenceWorkflow(), Theme::default()));

        $snapshot = file_get_contents(__DIR__.'/__snapshots__/sequence-workflow.svg');
        $this->assertNotFalse($snapshot);
        $this->assertSame(trim($snapshot), trim($svg));
    }

    public function testFragmentsActivationsBranchesAndSelfMessagesAreRenderableFeatures(): void
    {
        $diagram = (new SequenceDiagramBuilder())
            ->participant('User')
            ->participant('Api')
            ->message('User', 'Api', 'Start')
            ->activate('Api')
            ->message('Api', 'Api', 'Validate internally')
            ->message('Api', 'User', 'Done')
            ->deactivate('Api')
            ->block(SequenceBlockKind::Alt, 'happy path', 0, 2, [
                new SequenceBlockBranch('happy path', 0, 1),
                new SequenceBlockBranch('fallback', 2, 2),
            ])
            ->build();

        $scene = (new SequenceLayoutEngine())->layout($diagram, Theme::default());

        $paths = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof PathNode));
        $this->assertNotEmpty(array_filter($paths, static fn (PathNode $node): bool => str_contains($node->data, 'V')));

        $smallRects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode && $node->width < 12.0));
        $this->assertNotEmpty($smallRects);

        $wideRects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode && $node->width > 200.0));
        $this->assertNotEmpty($wideRects);

        $dashedSeparators = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof LineNode && LineStyle::Solid !== $node->style->lineStyle));
        $this->assertNotEmpty($dashedSeparators);

        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $this->assertNotEmpty(array_filter($texts, static fn (TextNode $node): bool => 'alt happy path' === $node->text));
        $this->assertNotEmpty(array_filter($texts, static fn (TextNode $node): bool => 'fallback' === $node->text));
        $this->assertNotEmpty(array_filter($texts, static fn (TextNode $node): bool => 'Validate internally' === $node->text));
    }

    public function testBlockFramesAreOutlinesWithoutBackgroundFill(): void
    {
        $theme = Theme::dark();
        $diagram = (new SequenceDiagramBuilder())
            ->participant('User')
            ->participant('Api')
            ->message('User', 'Api', 'Start')
            ->message('Api', 'User', 'Done')
            ->block(SequenceBlockKind::Alt, 'happy path', 0, 1)
            ->build();

        $scene = (new SequenceLayoutEngine())->layout($diagram, $theme);

        $rects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode));
        $frames = array_values(array_filter($rects, static fn (RectNode $node): bool => $theme->mutedTextColor === $node->style->stroke));
        $this->assertNotEmpty($frames);
        foreach ($frames as $frame) {
            $this->assertNull($frame->style->fill);
        }
        $this->assertEmpty(array_filter($rects, static fn (RectNode $node): bool => '#f8fafc' === $node->style->fill));
    }
}
