<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Docs;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Enforces the canonical structure of per-diagram-type documentation pages
 * (tools/diagram/_diagram-template.md in the workspace, outside this package): the
 * eight required sections in order, a complete
 * table of contents, and working cross-references.
 *
 * The migrated-pages map grows as pages are brought to the template. Once all
 * 15 type pages conform, every type key belongs here.
 */
#[CoversNothing]
final class DiagramDocStructureTest extends TestCase
{
    /**
     * Required H2 section title => its GitHub heading anchor, in canonical order.
     */
    private const array SECTIONS = [
        'Overview' => 'overview',
        'Format' => 'format',
        'Builder API' => 'builder-api',
        'Options' => 'options',
        'Themes' => 'themes',
        'Parse' => 'parse',
        'Debug' => 'debug',
    ];

    /**
     * Per-type documentation pages already migrated to the template.
     *
     * @return iterable<string, array{string}>
     */
    public static function migratedPages(): iterable
    {
        yield 'state' => ['diagrams/state-diagram.md'];
        yield 'venn' => ['diagrams/venn-diagram.md'];
        yield 'git' => ['diagrams/git-graph.md'];
        yield 'sequence' => ['diagrams/sequence-diagram.md'];
        yield 'flowchart' => ['diagrams/flowchart.md'];
        yield 'class' => ['diagrams/class-diagram.md'];
        yield 'er' => ['diagrams/er-diagram.md'];
        yield 'timeline' => ['diagrams/timeline-diagram.md'];
        yield 'journey' => ['diagrams/journey-diagram.md'];
        yield 'mindmap' => ['diagrams/mindmap-diagram.md'];
        yield 'requirement' => ['diagrams/requirement-diagram.md'];
        yield 'kanban' => ['diagrams/kanban-diagram.md'];
        yield 'block' => ['diagrams/block-diagram.md'];
        yield 'architecture' => ['diagrams/architecture-diagram.md'];
        yield 'c4' => ['diagrams/c4-diagram.md'];
    }

    #[DataProvider('migratedPages')]
    public function testPageHasAllRequiredSectionsInOrder(string $docFile): void
    {
        $content = $this->readDoc($docFile);

        $lastPosition = -1;
        foreach (array_keys(self::SECTIONS) as $section) {
            $position = strpos($content, "\n## {$section}\n");
            $this->assertNotFalse($position, "docs/{$docFile} is missing section: ## {$section}");
            $this->assertGreaterThan($lastPosition, $position, "docs/{$docFile} section out of order: ## {$section}");
            $lastPosition = $position;
        }
    }

    #[DataProvider('migratedPages')]
    public function testPageHasCompleteTableOfContents(string $docFile): void
    {
        $content = $this->readDoc($docFile);

        foreach (self::SECTIONS as $section => $anchor) {
            $this->assertStringContainsString("(#{$anchor})", $content, "docs/{$docFile} table of contents is missing a link to: ## {$section}");
        }
    }

    #[DataProvider('migratedPages')]
    public function testRelativeMarkdownLinksResolve(string $docFile): void
    {
        $content = $this->readDoc($docFile);

        // Relative links resolve against the page, not against docs/: once pages
        // live in sections, the two are no longer the same directory.
        $pageDir = \dirname(\dirname(__DIR__, 2).'/docs/'.$docFile);

        // Delimiter is `~`, not `#`. With `#...#` the pattern ended at the `#`
        // inside the lookahead, preg_match_all returned false, and this test
        // passed without ever checking a link.
        preg_match_all('~\]\((?!https?://|#)([^)#\s]+\.md)(?:#[^)]*)?\)~', $content, $matches);

        foreach ($matches[1] as $target) {
            $this->assertFileExists($pageDir.'/'.$target, "docs/{$docFile} links a missing doc: {$target}");
        }
    }

    private function readDoc(string $docFile): string
    {
        $path = \dirname(__DIR__, 2).'/docs/'.$docFile;
        $content = file_get_contents($path);
        $this->assertNotFalse($content, "cannot read docs/{$docFile}");

        return $content;
    }
}
