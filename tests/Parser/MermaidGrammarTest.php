<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser;

use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\MermaidDiagramParserInterface;
use Atelier\Diagram\Parser\MermaidGrammar;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MermaidGrammar::class)]
final class MermaidGrammarTest extends TestCase
{
    public function testParseForwardsSourceAndLimitsWithoutHeader(): void
    {
        $parser = new RecordingParser();
        $grammar = new MermaidGrammar('demo', ['demo'], $parser);
        $limits = new ParserInputLimits(maxSourceBytes: 123, maxLineBytes: 45);

        $model = $grammar->parse("demo\nvalue\n", $limits);

        $this->assertSame('demo', $model->name);
        $this->assertSame("demo\nvalue\n", $parser->source);
        $this->assertSame($limits, $parser->limits);
        $this->assertNull($parser->header);
    }

    public function testParseWithHeaderForwardsHeaderAndLimits(): void
    {
        $parser = new RecordingParser();
        $grammar = new MermaidGrammar('demo', ['demo'], $parser);
        $limits = new ParserInputLimits(maxSourceBytes: 123, maxLineBytes: 45);
        $header = new HeaderMatch(new \Atelier\Diagram\Parser\Line(1, 'demo'), 5);

        $model = $grammar->parseWithHeader("demo\nvalue\n", $header, $limits);

        $this->assertSame('demo', $model->name);
        $this->assertSame("demo\nvalue\n", $parser->source);
        $this->assertSame($limits, $parser->limits);
        $this->assertSame($header, $parser->header);
    }
}

final class RecordingParser implements MermaidDiagramParserInterface
{
    public ?string $source = null;
    public ?ParserInputLimits $limits = null;
    public ?HeaderMatch $header = null;

    public function parse(string $source, ?ParserInputLimits $limits = null, ?HeaderMatch $header = null): DiagramModel
    {
        $this->source = $source;
        $this->limits = $limits;
        $this->header = $header;

        return new class implements DiagramModel {
            public function __construct(
                public string $name = 'demo',
            ) {
            }
        };
    }
}
