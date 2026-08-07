<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Journey\JourneyActor;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Journey\JourneySection;
use Atelier\Diagram\Journey\JourneyTask;

final class JourneyDiagramSerializer
{
    public function serialize(JourneyDiagram $diagram): string
    {
        $lines = ['journey'];

        if (null !== $diagram->title) {
            $this->assertSerializableText($diagram->title->text, 'title');
            $lines[] = '    title '.$diagram->title->text;
        }

        foreach ($diagram->sections as $section) {
            $lines[] = '    '.$this->sectionLine($section);
            foreach ($section->tasks as $task) {
                $lines[] = '        '.$this->taskLine($task);
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function sectionLine(JourneySection $section): string
    {
        $this->assertSerializableText($section->title, 'section title');

        return 'section '.$section->title;
    }

    private function taskLine(JourneyTask $task): string
    {
        $this->assertSerializableText($task->text, 'task text');
        $actors = array_map(fn (JourneyActor $actor): string => $this->actorName($actor), $task->actors);

        return \sprintf('%s: %d: %s', $task->text, $task->score, implode(', ', $actors));
    }

    private function actorName(JourneyActor $actor): string
    {
        $this->assertSerializableText($actor->name, 'actor name');
        if (str_contains($actor->name, ',')) {
            throw new InvalidArgumentException(\sprintf('Cannot render journey diagram to Mermaid: actor name "%s" must not contain commas.', $actor->name));
        }

        return $actor->name;
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'journey diagram', $what, '/[\r\n]/', 'must not contain newlines');
    }
}
