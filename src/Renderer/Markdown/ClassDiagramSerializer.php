<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\ClassDiagram\ClassBox;
use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassRelation;

final class ClassDiagramSerializer
{
    public function serialize(ClassDiagram $diagram): string
    {
        $lines = ['classDiagram'];

        foreach ($diagram->classes as $class) {
            $lines[] = '    '.$this->classLine($class);
            foreach ($class->members as $member) {
                $this->assertSerializableText($member->text, \sprintf('member of class "%s"', $class->id));
                $lines[] = '    '.$class->id.' : '.$member->text;
            }
        }

        foreach ($diagram->relations as $relation) {
            $lines[] = '    '.$this->relationLine($relation);
        }

        return implode("\n", $lines)."\n";
    }

    private function classLine(ClassBox $class): string
    {
        $this->assertSerializableId($class->id, 'class id');

        return 'class '.$class->id;
    }

    private function relationLine(ClassRelation $relation): string
    {
        $this->assertSerializableId($relation->from, 'relation source id');
        $this->assertSerializableId($relation->to, 'relation target id');

        if (null === $relation->label) {
            return \sprintf('%s --> %s', $relation->from, $relation->to);
        }

        $this->assertSerializableText($relation->label->text, \sprintf('label of relation "%s" to "%s"', $relation->from, $relation->to));

        return \sprintf('%s --> %s : %s', $relation->from, $relation->to, $relation->label->text);
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'class diagram', $what);
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'class diagram', $what, '/[\r\n]/', 'contains unsupported control characters');
    }
}
