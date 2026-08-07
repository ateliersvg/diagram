<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Kanban;

use Atelier\Diagram\Kanban\KanbanDiagramBuilder;
use Atelier\Diagram\Layout\Kanban\KanbanLayoutEngine;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KanbanLayoutEngine::class)]
final class KanbanLayoutEngineTest extends TestCase
{
    public function testLaysOutColumnsAndCards(): void
    {
        $diagram = (new KanbanDiagramBuilder())
            ->title('Delivery board')
            ->column('todo', 'Todo')
            ->card('todo', 'REQ-1', 'Write parser')
            ->column('done', 'Done')
            ->card('done', 'CORE-1', 'Scene renderer')
            ->build();

        $scene = (new KanbanLayoutEngine())->layout($diagram, Theme::default());

        $this->assertGreaterThan(0.0, $scene->width);
        $this->assertGreaterThan(0.0, $scene->height);
        $this->assertGreaterThanOrEqual(6, \count(array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode)));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'Delivery board' === $node->text));
        $this->assertNotEmpty(array_filter($scene->nodes, static fn ($node): bool => $node instanceof TextNode && 'REQ-1' === $node->text));
    }

    public function testCardsHaveNoEdgeStrip(): void
    {
        $diagram = (new KanbanDiagramBuilder())
            ->column('todo', 'Todo')
            ->card('todo', 'REQ-1', 'Write parser')
            ->build();

        $scene = (new KanbanLayoutEngine())->layout($diagram, Theme::default());

        $strips = array_filter($scene->nodes, static fn ($node): bool => $node instanceof RectNode && 4.0 === $node->width);
        $this->assertEmpty($strips);
    }

    public function testWrapsLongCardLabels(): void
    {
        $diagram = (new KanbanDiagramBuilder())
            ->column('todo', 'Todo')
            ->card('todo', 'REQ-1', 'Write parser documentation and review generated SVG output carefully')
            ->build();

        $scene = (new KanbanLayoutEngine())->layout($diagram, Theme::default());
        $labelLines = array_filter(
            $scene->nodes,
            static fn ($node): bool => $node instanceof TextNode && str_contains($node->text, 'parser') || $node instanceof TextNode && str_contains($node->text, 'generated'),
        );

        $this->assertGreaterThanOrEqual(2, \count($labelLines));
    }
}
