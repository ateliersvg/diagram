<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Layout\Git\GitLayoutEngine;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Parser\GitGraphParser;
use Atelier\Diagram\Parser\Support\QuotedAttributeList;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Diagram\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Section-2.3 parser/builder equivalence: the reference history parsed from
 * Mermaid renders byte-identical SVG to the hand-built graph. Parser error
 * cases are covered in part 3.3, not here.
 */
#[CoversClass(GitGraphParser::class)]
#[CoversClass(QuotedAttributeList::class)]
final class GitGraphParserTest extends TestCase
{
    use ExampleFixtures;

    public function testParsedMermaidRendersIdenticallyToHandBuiltGraph(): void
    {
        self::loadExample('git-history.php');

        $theme = Theme::default();
        $engine = new GitLayoutEngine();
        $renderer = new SvgRenderer();

        $built = $renderer->render($engine->layout(buildGitHistory(), $theme));
        $parsed = $renderer->render($engine->layout((new GitGraphParser())->parse(gitHistoryMermaid()), $theme));

        $this->assertSame($built, $parsed);
    }

    public function testParsesCommitAttributesInEitherOrder(): void
    {
        $graph = (new GitGraphParser())->parse(<<<'MERMAID'
            gitGraph
                commit tag: "v1" id: "a1"
            MERMAID);

        $this->assertSame('a1', $graph->commits[0]->id);
        $this->assertSame('v1', $graph->commits[0]->tag);
    }

    public function testParsesTopToBottomHeaderDirection(): void
    {
        $graph = (new GitGraphParser())->parse("gitGraph TB:\ncommit\n");

        $this->assertSame(Direction::TopToBottom, $graph->direction);
    }

    public function testRejectsUnexpectedHeaderWithStableDiagnostic(): void
    {
        try {
            (new GitGraphParser())->parse("flowchart TD\ncommit\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected "gitGraph" header at line 1: "flowchart TD"', $exception->getMessage());
            $this->assertSame('parser.expected_header', $exception->getDiagnostic()->code);
            $this->assertSame('flowchart TD', $exception->getDiagnostic()->source?->content);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsUnsupportedStatementWithStableDiagnostic(): void
    {
        try {
            (new GitGraphParser())->parse("gitGraph\ncherry-pick id: \"a1\"\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Unsupported gitGraph syntax at line 2: "cherry-pick id: "a1""', $exception->getMessage());
            $this->assertSame('parser.unsupported_syntax', $exception->getDiagnostic()->code);
            $this->assertSame('cherry-pick id: "a1"', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsInvalidCommitAttributeWithStableDiagnostic(): void
    {
        try {
            (new GitGraphParser())->parse("gitGraph\ncommit type: \"NORMAL\"\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Invalid commit attribute, expected id: "..." or tag: "..." at line 2: "commit type: "NORMAL""', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
            $this->assertSame('commit type: "NORMAL"', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateCommitAttributeWithStableDiagnostic(): void
    {
        try {
            (new GitGraphParser())->parse("gitGraph\ncommit id: \"a1\" id: \"a2\"\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Duplicate commit attribute "id" at line 2: "commit id: "a1" id: "a2""', $exception->getMessage());
            $this->assertSame('parser.syntax_error', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateBranchWithStableDiagnostic(): void
    {
        try {
            (new GitGraphParser())->parse("gitGraph\nbranch feature\nbranch feature\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Branch "feature" already exists at line 3: "branch feature"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('branch feature', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsDuplicateCommitIdWithStableDiagnostic(): void
    {
        try {
            (new GitGraphParser())->parse("gitGraph\ncommit id: \"a1\"\ncommit id: \"a1\"\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Commit id "a1" is already used by another commit at line 3: "commit id: "a1""', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('commit id: "a1"', $exception->getDiagnostic()->source?->content);
            $this->assertSame(3, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(3, $exception->getDiagnostic()->span->endLine);
        }
    }

    public function testRejectsUnknownBranchCheckoutWithStableDiagnostic(): void
    {
        try {
            (new GitGraphParser())->parse("gitGraph\ncheckout feature\n");
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Cannot checkout unknown branch "feature" at line 2: "checkout feature"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame('checkout feature', $exception->getDiagnostic()->source?->content);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
        }
    }
}
