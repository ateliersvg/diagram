<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Timeline\TimelineDiagram;

final class TimelineDiagramSerializer
{
    public function serialize(TimelineDiagram $diagram): string
    {
        $lines = ['timeline'];

        if (null !== $diagram->title) {
            $this->assertSerializableText($diagram->title->text, 'timeline title');
            $lines[] = '    title '.$diagram->title->text;
        }

        foreach ($diagram->sections as $section) {
            $this->assertSerializableText($section->title, 'timeline section');
            $lines[] = '    section '.$section->title;

            foreach ($section->events as $event) {
                $this->assertSerializableText($event->label, 'timeline event label');
                $this->assertSerializableText($event->date, 'timeline event date');
                $lines[] = '        '.$event->label.' : '.$event->date;
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'timeline diagram', $what, '/[\r\n]/', 'contains unsupported control characters');
    }
}
