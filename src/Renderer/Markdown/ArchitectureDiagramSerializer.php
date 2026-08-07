<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Architecture\ArchitectureGroup;
use Atelier\Diagram\Architecture\ArchitectureNode;
use Atelier\Diagram\Architecture\ArchitectureRelationship;

final class ArchitectureDiagramSerializer
{
    public function serialize(ArchitectureDiagram $diagram): string
    {
        $lines = ['architecture'];

        if (null !== $diagram->title) {
            $this->assertSerializableText($diagram->title->text, 'title');
            $lines[] = '    title '.$diagram->title->text;
        }

        $nodesByGroup = $this->nodesByGroup($diagram);
        foreach ($diagram->groups as $group) {
            $lines = [...$lines, ...$this->groupLines($group, $nodesByGroup[$group->id] ?? [])];
        }

        foreach ($nodesByGroup[''] ?? [] as $node) {
            $lines[] = '    '.$this->nodeLine($node);
        }

        foreach ($diagram->relationships as $relationship) {
            $lines[] = '    '.$this->relationshipLine($relationship);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, list<ArchitectureNode>>
     */
    private function nodesByGroup(ArchitectureDiagram $diagram): array
    {
        $nodesByGroup = [];
        foreach ($diagram->nodes as $node) {
            $nodesByGroup[$node->groupId ?? ''][] = $node;
        }

        return $nodesByGroup;
    }

    /**
     * @param list<ArchitectureNode> $nodes
     *
     * @return list<string>
     */
    private function groupLines(ArchitectureGroup $group, array $nodes): array
    {
        $this->assertSerializableId($group->id, 'group id');
        $this->assertSerializableText($group->label->text, \sprintf('label of group "%s"', $group->id));

        $lines = ['    group '.$group->id.' ['.$group->label->text.']'];
        foreach ($nodes as $node) {
            $lines[] = '        '.$this->nodeLine($node);
        }

        return $lines;
    }

    private function nodeLine(ArchitectureNode $node): string
    {
        $this->assertSerializableId($node->id, 'node id');
        $this->assertSerializableText($node->label->text, \sprintf('label of node "%s"', $node->id));

        return $node->kind->value.' '.$node->id.' ['.$node->label->text.']';
    }

    private function relationshipLine(ArchitectureRelationship $relationship): string
    {
        $this->assertSerializableId($relationship->from, 'relationship source id');
        $this->assertSerializableId($relationship->to, 'relationship target id');

        $line = $relationship->from.' -> '.$relationship->to;
        if (null === $relationship->label) {
            return $line;
        }

        $this->assertSerializableText($relationship->label->text, \sprintf('label of relationship "%s" to "%s"', $relationship->from, $relationship->to));

        return $line.' : '.$relationship->label->text;
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'architecture diagram', $what);
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'architecture diagram', $what, '/[\r\n\[\]]/', 'contains unsupported control characters');
    }
}
