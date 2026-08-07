<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Journey;

use Atelier\Diagram\Journey\JourneyActor;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Journey\JourneyDiagramBuilder;
use Atelier\Diagram\Journey\JourneySection;
use Atelier\Diagram\Journey\JourneyTask;
use Atelier\Diagram\Layout\Journey\JourneyLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JourneyLayoutEngine::class)]
#[CoversClass(JourneyDiagramBuilder::class)]
#[CoversClass(JourneyDiagram::class)]
#[CoversClass(JourneySection::class)]
#[CoversClass(JourneyTask::class)]
#[CoversClass(JourneyActor::class)]
final class JourneyLayoutEngineTest extends TestCase
{
    public function testRendersJourneyDiagramToSvg(): void
    {
        $diagram = (new JourneyDiagramBuilder())
            ->title('Checkout experience')
            ->section('Browse')
            ->task('Open product page', 5, ['Customer'])
            ->section('Payment')
            ->task('Enter card', 3, ['Customer', 'PSP'])
            ->build();

        $svg = (new SvgRenderer())->render((new JourneyLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('Checkout experience', $svg);
        $this->assertStringContainsString('Browse', $svg);
        $this->assertStringContainsString('Open product page', $svg);
        $this->assertStringContainsString('Customer + PSP', $svg);
    }

    public function testScoreBandsCoverEveryColorAndFill(): void
    {
        $builder = (new JourneyDiagramBuilder())->section('Spectrum');
        foreach ([1, 2, 3, 4, 5] as $score) {
            $builder->task('Step '.$score, $score, ['Customer']);
        }

        $scene = (new JourneyLayoutEngine())->layout($builder->build(), Theme::default());

        $scoreTexts = array_values(array_filter(
            $scene->nodes,
            static fn ($node): bool => $node instanceof TextNode && \in_array($node->text, ['1', '2', '3', '4', '5'], true),
        ));

        $this->assertCount(5, $scoreTexts);
    }

    public function testScoreStylingFollowsTheme(): void
    {
        $theme = Theme::mono();
        $builder = (new JourneyDiagramBuilder())->section('Spectrum');
        foreach ([1, 2, 3, 4, 5] as $score) {
            $builder->task('Step '.$score, $score, ['Customer']);
        }

        $scene = (new JourneyLayoutEngine())->layout($builder->build(), $theme);

        $legacyScale = [
            '#dc2626', '#ea580c', '#d97706', '#16a34a', '#059669',
            '#fee2e2', '#ffedd5', '#fef3c7', '#dcfce7', '#d1fae5',
        ];
        $rects = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode));
        $this->assertEmpty(array_filter(
            $rects,
            static fn (RectNode $node): bool => \in_array($node->style->fill, $legacyScale, true)
                || \in_array($node->style->stroke, $legacyScale, true),
        ));

        $washes = [];
        foreach ($rects as $rect) {
            if (\is_string($rect->style->fill) && str_starts_with($rect->style->fill, $theme->accentColors[0]) && $theme->accentColors[0] !== $rect->style->fill) {
                $washes[$rect->style->fill] = true;
            }
        }
        $this->assertCount(5, $washes);

        $scoreTexts = array_values(array_filter(
            $scene->nodes,
            static fn ($node): bool => $node instanceof TextNode && \in_array($node->text, ['1', '2', '3', '4', '5'], true),
        ));
        $this->assertCount(5, $scoreTexts);
        foreach ($scoreTexts as $scoreText) {
            $this->assertSame($theme->backgroundColor, $scoreText->style->fill);
        }
    }

    public function testMixFallsBackWhenNodeFillIsNotSixDigitHex(): void
    {
        $diagram = (new JourneyDiagramBuilder())
            ->section('Browse')
            ->task('Open product page', 5, ['Customer'])
            ->build();

        $theme = new Theme(
            backgroundColor: '#ffffff',
            nodeFillColor: '#abc',
            nodeStrokeColor: '#334155',
            textColor: '#1e293b',
            mutedTextColor: '#64748b',
            accentColors: ['#2563eb'],
            fontFamily: 'Helvetica, Arial, sans-serif',
            fontSize: 14.0,
            spacingUnit: 8.0,
        );

        $scene = (new JourneyLayoutEngine())->layout($diagram, $theme);

        $laneFills = array_values(array_filter(
            $scene->nodes,
            static fn ($node): bool => $node instanceof RectNode && '#abc' === $node->style->fill,
        ));

        $this->assertNotEmpty($laneFills);
    }
}
