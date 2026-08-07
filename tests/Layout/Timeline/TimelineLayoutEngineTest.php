<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Timeline;

use Atelier\Diagram\Layout\Timeline\TimelineLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;
use Atelier\Diagram\Timeline\TimelineEvent;
use Atelier\Diagram\Timeline\TimelineSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimelineLayoutEngine::class)]
#[CoversClass(TimelineDiagram::class)]
#[CoversClass(TimelineDiagramBuilder::class)]
#[CoversClass(TimelineSection::class)]
#[CoversClass(TimelineEvent::class)]
final class TimelineLayoutEngineTest extends TestCase
{
    public function testDateTextOnAccentPillFollowsTheme(): void
    {
        $theme = Theme::dark();
        $diagram = (new TimelineDiagramBuilder())
            ->section('Discovery')
            ->event('Research', 'Jan')
            ->build();

        $scene = (new TimelineLayoutEngine())->layout($diagram, $theme);

        $texts = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode));
        $this->assertEmpty(array_filter($texts, static fn (TextNode $node): bool => '#ffffff' === $node->style->fill));
        $dates = array_values(array_filter($texts, static fn (TextNode $node): bool => 'Jan' === $node->text));
        $this->assertNotEmpty($dates);
        $this->assertSame($theme->backgroundColor, $dates[0]->style->fill);
    }

    public function testRendersTimelineToSvg(): void
    {
        $diagram = (new TimelineDiagramBuilder())
            ->title('Product launch')
            ->section('Discovery')
            ->event('Research complete', '2026-01')
            ->event('Prototype review', '2026-02')
            ->section('Build')
            ->event('Public launch', '2026-06')
            ->build();

        $svg = (new SvgRenderer())->render((new TimelineLayoutEngine())->layout($diagram, Theme::default()));

        $this->assertStringContainsString('Product launch', $svg);
        $this->assertStringContainsString('Discovery', $svg);
        $this->assertStringContainsString('Research complete', $svg);
        $this->assertStringContainsString('2026-06', $svg);
    }
}
