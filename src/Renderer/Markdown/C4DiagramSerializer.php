<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\C4\C4Boundary;
use Atelier\Diagram\C4\C4Diagram;
use Atelier\Diagram\C4\C4Element;
use Atelier\Diagram\C4\C4ElementKind;
use Atelier\Diagram\C4\C4Relationship;

final class C4DiagramSerializer
{
    public function serialize(C4Diagram $diagram): string
    {
        $lines = [$diagram->view->value];

        if (null !== $diagram->title) {
            $this->assertSerializableText($diagram->title->text, 'title');
            $lines[] = '    title '.$diagram->title->text;
        }

        $elementsByBoundary = $this->elementsByBoundary($diagram);
        foreach ($diagram->boundaries as $boundary) {
            $lines = [...$lines, ...$this->boundaryLines($boundary, $elementsByBoundary[$boundary->id] ?? [])];
        }

        foreach ($elementsByBoundary[''] ?? [] as $element) {
            $lines[] = '    '.$this->elementLine($element);
        }

        foreach ($diagram->relationships as $relationship) {
            $lines[] = '    '.$this->relationshipLine($relationship);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, list<C4Element>>
     */
    private function elementsByBoundary(C4Diagram $diagram): array
    {
        $elementsByBoundary = [];
        foreach ($diagram->elements as $element) {
            $elementsByBoundary[$element->boundaryId ?? ''][] = $element;
        }

        return $elementsByBoundary;
    }

    /**
     * @param list<C4Element> $elements
     *
     * @return list<string>
     */
    private function boundaryLines(C4Boundary $boundary, array $elements): array
    {
        $this->assertSerializableId($boundary->id, 'boundary id');
        $this->assertSerializableText($boundary->label->text, \sprintf('label of boundary "%s"', $boundary->id));

        $lines = ['    System_Boundary('.$boundary->id.', "'.$boundary->label->text.'") {'];
        foreach ($elements as $element) {
            $lines[] = '        '.$this->elementLine($element);
        }
        $lines[] = '    }';

        return $lines;
    }

    private function elementLine(C4Element $element): string
    {
        $this->assertSerializableId($element->id, 'element id');
        $this->assertSerializableText($element->label->text, \sprintf('label of element "%s"', $element->id));

        $args = [$element->id, '"'.$element->label->text.'"'];
        if ($this->supportsTechnology($element->kind)) {
            if (null !== $element->technology) {
                $this->assertSerializableText($element->technology->text, \sprintf('technology of element "%s"', $element->id));
                $args[] = '"'.$element->technology->text.'"';
            }
            if (null !== $element->description) {
                if (null === $element->technology) {
                    $args[] = '""';
                }
                $this->assertSerializableText($element->description->text, \sprintf('description of element "%s"', $element->id));
                $args[] = '"'.$element->description->text.'"';
            }

            return $element->kind->macro().'('.implode(', ', $args).')';
        }

        if (null !== $element->description) {
            $this->assertSerializableText($element->description->text, \sprintf('description of element "%s"', $element->id));
            $args[] = '"'.$element->description->text.'"';
        }

        return $element->kind->macro().'('.implode(', ', $args).')';
    }

    private function relationshipLine(C4Relationship $relationship): string
    {
        $this->assertSerializableId($relationship->from, 'relationship source id');
        $this->assertSerializableId($relationship->to, 'relationship target id');
        $this->assertSerializableText($relationship->label->text, \sprintf('label of relationship "%s" to "%s"', $relationship->from, $relationship->to));

        $args = [$relationship->from, $relationship->to, '"'.$relationship->label->text.'"'];
        if (null !== $relationship->technology) {
            $this->assertSerializableText($relationship->technology->text, \sprintf('technology of relationship "%s" to "%s"', $relationship->from, $relationship->to));
            $args[] = '"'.$relationship->technology->text.'"';
        }

        return 'Rel('.implode(', ', $args).')';
    }

    private function supportsTechnology(C4ElementKind $kind): bool
    {
        return match ($kind) {
            C4ElementKind::Person, C4ElementKind::PersonExternal, C4ElementKind::System, C4ElementKind::SystemExternal => false,
            default => true,
        };
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'C4 diagram', $what);
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'C4 diagram', $what, '/[\r\n"]/', 'contains unsupported control characters');
    }
}
