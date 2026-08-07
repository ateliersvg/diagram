<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Flow\FlowEdge;
use Atelier\Diagram\Flow\FlowNode;
use Atelier\Diagram\Flow\FlowSubgraph;
use Atelier\Diagram\Model\Direction;

/**
 * Serializes Flowchart to canonical Mermaid flowchart source.
 *
 * @internal used by MarkdownRenderer
 */
final class FlowchartSerializer
{
    public function serialize(Flowchart $flowchart): string
    {
        $lines = [Direction::LeftToRight === $flowchart->direction ? 'flowchart LR' : 'flowchart TD'];

        if (null !== $flowchart->title) {
            $this->assertSerializableText($flowchart->title->text, 'title');
            $lines[] = '    title '.$flowchart->title->text;
        }

        foreach ($this->topLevelSubgraphs($flowchart) as $subgraph) {
            $lines = [...$lines, ...$this->subgraphLines($flowchart, $subgraph, 1)];
        }

        foreach ($flowchart->nodes as $node) {
            if ($this->nodeBelongsToSubgraph($flowchart, $node->id)) {
                continue;
            }
            $lines[] = '    '.$this->nodeLine($node);
        }

        foreach ($flowchart->edges as $edge) {
            $lines[] = '    '.$this->edgeLine($edge);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function subgraphLines(Flowchart $flowchart, FlowSubgraph $subgraph, int $indentLevel): array
    {
        $this->assertSerializableId($subgraph->id, 'subgraph id');
        $this->assertSerializableText($subgraph->label, \sprintf('label of subgraph "%s"', $subgraph->id));

        $indent = str_repeat('    ', $indentLevel);
        $lines = [$indent.\sprintf('subgraph %s [%s]', $subgraph->id, $subgraph->label)];
        $children = $this->childSubgraphs($flowchart, $subgraph->id);
        $childByNode = [];
        foreach ($children as $child) {
            foreach ($child->nodeIds as $nodeId) {
                $childByNode[$nodeId] = $child;
            }
        }

        $emittedChildren = [];
        foreach ($subgraph->nodeIds as $nodeId) {
            $child = $childByNode[$nodeId] ?? null;
            if (null !== $child) {
                if (!isset($emittedChildren[$child->id])) {
                    $lines = [...$lines, ...$this->subgraphLines($flowchart, $child, $indentLevel + 1)];
                    $emittedChildren[$child->id] = true;
                }
                continue;
            }
            $lines[] = str_repeat('    ', $indentLevel + 1).$this->nodeLine($this->nodeById($flowchart, $nodeId));
        }
        $lines[] = $indent.'end';

        return $lines;
    }

    /**
     * @return list<FlowSubgraph>
     */
    private function topLevelSubgraphs(Flowchart $flowchart): array
    {
        return array_values(array_filter(
            $flowchart->subgraphs,
            static fn (FlowSubgraph $subgraph): bool => null === $subgraph->parentId,
        ));
    }

    /**
     * @return list<FlowSubgraph>
     */
    private function childSubgraphs(Flowchart $flowchart, string $parentId): array
    {
        return array_values(array_filter(
            $flowchart->subgraphs,
            static fn (FlowSubgraph $subgraph): bool => $subgraph->parentId === $parentId,
        ));
    }

    private function nodeBelongsToSubgraph(Flowchart $flowchart, string $nodeId): bool
    {
        foreach ($flowchart->subgraphs as $subgraph) {
            if (\in_array($nodeId, $subgraph->nodeIds, true)) {
                return true;
            }
        }

        return false;
    }

    private function nodeById(Flowchart $flowchart, string $id): FlowNode
    {
        foreach ($flowchart->nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        throw new InvalidArgumentException(\sprintf('Cannot render flowchart to Mermaid: subgraph references unknown node "%s".', $id));
    }

    private function nodeLine(FlowNode $node): string
    {
        $this->assertSerializableId($node->id, 'node id');
        $this->assertSerializableText($node->label, \sprintf('label of node "%s"', $node->id));

        return \sprintf('%s[%s]', $node->id, $node->label);
    }

    private function edgeLine(FlowEdge $edge): string
    {
        $this->assertSerializableId($edge->from, 'edge source id');
        $this->assertSerializableId($edge->to, 'edge target id');

        if (null === $edge->label) {
            return \sprintf('%s --> %s', $edge->from, $edge->to);
        }

        $this->assertSerializableText($edge->label->text, \sprintf('label of edge "%s" to "%s"', $edge->from, $edge->to));

        return \sprintf('%s -->|%s| %s', $edge->from, $edge->label->text, $edge->to);
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'flowchart', $what);
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'flowchart', $what, '/[\r\n\]\|]/', 'contains unsupported control characters');
    }
}
