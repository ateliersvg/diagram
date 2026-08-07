<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Layout\State\StateLayoutEngine;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Parser\StateDiagramParser;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateDiagramParser::class)]
final class StateDiagramParserTest extends TestCase
{
    use ExampleFixtures;

    /**
     * Parser/builder equivalence: the Mermaid snippet and the hand-built
     * diagram describe the same machine, so both entry points must render
     * byte-identical SVG.
     */
    public function testParsedDiagramRendersIdenticallyToHandBuiltDiagram(): void
    {
        self::loadExample('state-machine.php');

        $engine = new StateLayoutEngine();
        $renderer = new SvgRenderer();
        $theme = Theme::default();

        $built = $renderer->render($engine->layout(buildStateMachineDiagram(), $theme));
        $parsed = $renderer->render($engine->layout(
            (new StateDiagramParser())->parse(stateMachineMermaid()),
            $theme,
        ));

        $this->assertSame($built, $parsed);
    }

    public function testUnexpectedHeaderThrowsAtSourceLine(): void
    {
        try {
            (new StateDiagramParser())->parse("classDiagram\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected "stateDiagram-v2" or "stateDiagram" header at line 1: "classDiagram"', $exception->getMessage());
            $this->assertSame('parser.expected_header', $exception->getDiagnostic()->code);
            $this->assertSame('classDiagram', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testMissingHeaderThrowsAtLineOne(): void
    {
        try {
            (new StateDiagramParser())->parse("%% comment\n\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Missing "stateDiagram-v2" header at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.missing_header', $exception->getDiagnostic()->code);
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testUnsupportedDirectionThrowsAtSourceLine(): void
    {
        try {
            (new StateDiagramParser())->parse("stateDiagram-v2\ndirection RL\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported direction "RL", expected TB or LR at line 2: "direction RL"', $exception->getMessage());
            $this->assertSame('parser.invalid_enum', $exception->getDiagnostic()->code);
            $this->assertSame('direction RL', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testBuildErrorThrowsAtHeaderLine(): void
    {
        try {
            (new StateDiagramParser())->parse("stateDiagram-v2\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('A state diagram must declare at least one state at line 1: "stateDiagram-v2"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('stateDiagram-v2', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testParsesLeftToRightDirection(): void
    {
        $diagram = (new StateDiagramParser())->parse("stateDiagram-v2\ndirection LR\nA --> B\n");

        $this->assertSame(Direction::LeftToRight, $diagram->direction);
    }

    public function testUnsupportedSyntaxThrowsAtSourceLine(): void
    {
        try {
            (new StateDiagramParser())->parse("stateDiagram-v2\nA --> B\nstate foo <<choice>>\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported state diagram syntax at line 3: "state foo <<choice>>"', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('state foo <<choice>>', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testParsesViaHeaderEntryPoint(): void
    {
        $source = "stateDiagram-v2\ndirection LR\n[*] --> A\nA --> B : go\nB --> [*]\n";
        $header = HeaderParser::firstSignificantLine($source);

        $diagram = (new StateDiagramParser())->parse($source, null, $header);

        $this->assertSame(Direction::LeftToRight, $diagram->direction);
        $this->assertNotSame([], $diagram->transitions);
    }
}
