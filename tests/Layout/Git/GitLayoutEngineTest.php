<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Layout\Git;

use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Git\Branch;
use Atelier\Diagram\Git\Commit;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Layout\Git\GitLayoutEngine;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Section-2.3 end-to-end snapshot: builder -> layout -> SvgRenderer against
 * a frozen fixture. Builder validation and layout edge cases are covered in
 * part 3.3, not here.
 */
#[CoversClass(GitLayoutEngine::class)]
final class GitLayoutEngineTest extends TestCase
{
    use ExampleFixtures;

    public function testGitHistoryRenderingMatchesFrozenSnapshot(): void
    {
        self::loadExample('git-history.php');

        $scene = (new GitLayoutEngine())->layout(buildGitHistory('Release history'), Theme::default());
        $svg = (new SvgRenderer())->render($scene);

        $snapshot = file_get_contents(__DIR__.'/__snapshots__/git-graph.svg');
        $this->assertNotFalse($snapshot);
        $this->assertSame(trim($snapshot), trim($svg));
    }

    public function testCrossLaneEdgesUseRoundedOrthogonalPaths(): void
    {
        self::loadExample('git-history.php');

        $scene = (new GitLayoutEngine())->layout(buildGitHistory('Release history'), Theme::default());
        $paths = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof PathNode));

        $this->assertCount(4, $paths);
        foreach ($paths as $path) {
            self::assertStringContainsString(' Q ', $path->data);
            self::assertStringNotContainsString(' C ', $path->data);
        }
    }

    public function testTopToBottomCrossLaneEdgesUseRoundedOrthogonalPaths(): void
    {
        self::loadExample('git-history.php');

        $history = buildGitHistory('Release history');
        $graph = new GitGraph(Direction::TopToBottom, $history->branches, $history->commits, $history->title, $history->showLegend);

        $scene = (new GitLayoutEngine())->layout($graph, Theme::default());
        $paths = array_values(array_filter($scene->nodes, static fn ($node): bool => $node instanceof PathNode));

        $this->assertCount(4, $paths);
        foreach ($paths as $path) {
            self::assertStringContainsString(' Q ', $path->data);
            self::assertStringContainsString(' V ', $path->data);
            self::assertStringContainsString(' H ', $path->data);
            self::assertStringNotContainsString(' C ', $path->data);
        }
    }

    public function testRejectsCommitOnUnknownBranch(): void
    {
        $graph = new GitGraph(branches: [new Branch('main')], commits: [new Commit('c1', 'ghost')]);

        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('Commit "c1" references unknown branch "ghost".');

        (new GitLayoutEngine())->layout($graph, Theme::default());
    }

    public function testRejectsCommitWithUnknownParent(): void
    {
        $graph = new GitGraph(branches: [new Branch('main')], commits: [new Commit('c1', 'main', parents: ['missing'])]);

        $this->expectException(InvalidDiagramException::class);
        $this->expectExceptionMessage('Commit "c1" references unknown parent "missing".');

        (new GitLayoutEngine())->layout($graph, Theme::default());
    }
}
