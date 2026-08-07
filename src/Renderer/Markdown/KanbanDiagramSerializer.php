<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Kanban\KanbanDiagram;

final class KanbanDiagramSerializer
{
    public function serialize(KanbanDiagram $diagram): string
    {
        $lines = ['kanban'];

        if (null !== $diagram->title) {
            $this->assertSerializableText($diagram->title->text, 'title');
            $lines[] = '    title '.$diagram->title->text;
        }

        foreach ($diagram->columns as $column) {
            $this->assertSerializableText($column->label, 'column label');
            $lines[] = \sprintf('    %s [%s]', $column->id, $column->label);

            foreach ($column->cards as $card) {
                $this->assertSerializableText($card->label, 'card label');
                $lines[] = \sprintf('        %s [%s]', $card->id, $card->label);
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'kanban', $what, '/[\r\n]/', 'contains unsupported control characters');
        MermaidSerializable::text($text, 'kanban', $what, '/\]/', 'contains unsupported bracket syntax');
    }
}
