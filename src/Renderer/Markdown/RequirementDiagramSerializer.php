<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Requirement\RequirementNode;
use Atelier\Diagram\Requirement\RequirementRelationship;

final class RequirementDiagramSerializer
{
    public function serialize(RequirementDiagram $diagram): string
    {
        $lines = ['requirementDiagram'];

        foreach ($diagram->nodes as $node) {
            $lines = [...$lines, ...$this->nodeLines($node)];
        }

        foreach ($diagram->relationships as $relationship) {
            $lines[] = '    '.$this->relationshipLine($relationship);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function nodeLines(RequirementNode $node): array
    {
        $this->assertSerializableId($node->id, 'node id');
        $lines = ['    '.$node->kind->value.' '.$node->id.' {'];

        foreach ($node->fields as $name => $value) {
            $this->assertSerializableFieldName($name);
            $this->assertSerializableText($value, \sprintf('field "%s" of node "%s"', $name, $node->id));
            $lines[] = '        '.$name.': '.$value;
        }

        $lines[] = '    }';

        return $lines;
    }

    private function relationshipLine(RequirementRelationship $relationship): string
    {
        $this->assertSerializableId($relationship->from, 'relationship source id');
        $this->assertSerializableId($relationship->to, 'relationship target id');

        return \sprintf('%s - %s -> %s', $relationship->from, $relationship->kind->value, $relationship->to);
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'requirement diagram', $what);
    }

    private function assertSerializableFieldName(string $name): void
    {
        MermaidSerializable::fieldName($name, 'requirement diagram');
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'requirement diagram', $what, '/[\r\n]/', 'contains unsupported control characters');
    }
}
