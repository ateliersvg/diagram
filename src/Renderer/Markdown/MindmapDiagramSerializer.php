<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Mindmap\MindmapNode;

final class MindmapDiagramSerializer
{
    public function serialize(MindmapDiagram $diagram): string
    {
        return implode("\n", ['mindmap', ...$this->nodeLines($diagram->root, 1, true)])."\n";
    }

    /**
     * @return list<string>
     */
    private function nodeLines(MindmapNode $node, int $depth, bool $root = false): array
    {
        $this->assertSerializableText($node->label, $root ? 'root label' : 'node label');

        $line = str_repeat('  ', $depth).($root ? 'root(('.$node->label.'))' : $node->label);
        $lines = [$line];

        foreach ($node->children as $child) {
            $lines = [...$lines, ...$this->nodeLines($child, $depth + 1)];
        }

        return $lines;
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'mindmap', $what, '/[\r\n]/', 'contains unsupported control characters');
        MermaidSerializable::text($text, 'mindmap', $what, '/\(\(|\)\)/', 'contains unsupported shape syntax');
    }
}
