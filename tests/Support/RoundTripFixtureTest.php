<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Support;

use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Renderer\Markdown\MarkdownRenderer;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MermaidParser::class)]
#[CoversClass(MarkdownRenderer::class)]
#[CoversClass(SvgRenderer::class)]
final class RoundTripFixtureTest extends TestCase
{
    #[DataProvider('canonicalSources')]
    public function testCanonicalMermaidSourceRoundTrip(string $source): void
    {
        RoundTripFixture::assertCanonicalMermaidSourceRoundTrip($this, $source);
    }

    #[DataProvider('canonicalSources')]
    public function testMarkdownFencedMermaidSourceRoundTrip(string $source): void
    {
        RoundTripFixture::assertMarkdownFencedMermaidSourceRoundTrip($this, $source);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function canonicalSources(): iterable
    {
        yield 'state' => ["stateDiagram-v2\n    [*] --> Idle\n    Idle --> Done : finish\n"];
        yield 'git' => ["gitGraph\n    commit id: \"a1\"\n"];
        yield 'sequence' => ["sequenceDiagram\n    participant User\n    participant Api\n    User->>Api: Pay\n"];
        yield 'flowchart' => ["flowchart TD\n    A[Cart]\n    B[Payment]\n    A -->|pay| B\n"];
        yield 'class' => ["classDiagram\n    class User\n    User : +email string\n"];
        yield 'er' => ["erDiagram\n    CUSTOMER {\n        string email\n    }\n    CUSTOMER ||--o{ ORDER : places\n"];
        yield 'timeline' => ["timeline\n    section Discovery\n        Research complete : 2026-01\n"];
        yield 'journey' => ["journey\n    section Browse\n        Open product page: 5: Customer\n"];
        yield 'mindmap' => ["mindmap\n  root((Atelier))\n    Layout\n"];
        yield 'requirement' => ["requirementDiagram\n    requirement checkout {\n        id: REQ-1\n        text: Customer can checkout\n    }\n"];
        yield 'kanban' => ["kanban\n    todo [Todo]\n        REQ-1 [Write parser]\n"];
        yield 'block' => ["block\n    block Solver [LayoutSolver]\n    block Grid [Grid]\n    Solver -> Grid : solves\n"];
        yield 'architecture' => ["architecture\n    component App [Frontend app]\n    component Api [Checkout API]\n    App -> Api : calls\n"];
        yield 'c4' => ["C4Container\n    Person(buyer, \"Buyer\")\n    Container(api, \"API\", \"PHP\")\n    Rel(buyer, api, \"uses\")\n"];
    }
}
