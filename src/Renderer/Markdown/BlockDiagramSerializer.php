<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Exception\InvalidArgumentException;

final class BlockDiagramSerializer
{
    public function serialize(BlockDiagram $diagram): string
    {
        $lines = ['block'];

        if (null !== $diagram->title) {
            $this->assertSerializableText($diagram->title->text, 'block title');
            $lines[] = '    title '.$diagram->title->text;
        }

        $groups = $this->groupsById($diagram);
        $renderedGroups = [];
        foreach ($diagram->nodes as $node) {
            if (null !== $node->groupId) {
                if (!isset($renderedGroups[$node->groupId])) {
                    $group = $groups[$node->groupId];
                    $this->assertSerializableId($group->id, 'block group id');
                    $this->assertSerializableText($group->label, 'block group label');
                    $lines[] = '    group '.$group->id.' ['.$group->label.']';
                    foreach ($group->nodeIds as $nodeId) {
                        $groupNode = $this->findNode($diagram, $nodeId);
                        $this->assertSerializableId($groupNode->id, 'block id');
                        $this->assertSerializableText($groupNode->label, 'block label');
                        $lines[] = '        block '.$groupNode->id.' ['.$groupNode->label.']';
                    }
                    $lines[] = '    end';
                    $renderedGroups[$node->groupId] = true;
                }
                continue;
            }

            $this->assertSerializableId($node->id, 'block id');
            $this->assertSerializableText($node->label, 'block label');
            $lines[] = '    block '.$node->id.' ['.$node->label.']';
        }

        foreach ($diagram->relationships as $relationship) {
            $this->assertSerializableId($relationship->from, 'block relationship source');
            $this->assertSerializableId($relationship->to, 'block relationship target');
            $line = '    '.$relationship->from.' -> '.$relationship->to;
            if (null !== $relationship->label) {
                $this->assertSerializableText($relationship->label->text, 'block relationship label');
                $line .= ' : '.$relationship->label->text;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines)."\n";
    }

    private function findNode(BlockDiagram $diagram, string $id): \Atelier\Diagram\Block\BlockNode
    {
        foreach ($diagram->nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        throw new InvalidArgumentException(\sprintf('Cannot render block diagram to Mermaid: unknown block "%s".', $id));
    }

    /**
     * @return array<string, \Atelier\Diagram\Block\BlockGroup>
     */
    private function groupsById(BlockDiagram $diagram): array
    {
        $groups = [];
        foreach ($diagram->groups as $group) {
            $groups[$group->id] = $group;
        }

        return $groups;
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'block diagram', $what, '/[\r\n\]]/', 'contains unsupported control characters');
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'block diagram', $what);
    }
}
