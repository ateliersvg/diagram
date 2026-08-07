<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Er\ErAttribute;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErEntity;
use Atelier\Diagram\Er\ErRelationship;

final class ErDiagramSerializer
{
    public function serialize(ErDiagram $diagram): string
    {
        $lines = ['erDiagram'];

        foreach ($diagram->entities as $entity) {
            $lines = [...$lines, ...$this->entityLines($entity)];
        }

        foreach ($diagram->relationships as $relationship) {
            $lines[] = '    '.$this->relationshipLine($relationship);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function entityLines(ErEntity $entity): array
    {
        $this->assertSerializableId($entity->id, 'entity id');

        if ([] === $entity->attributes) {
            return ['    '.$entity->id.' {', '    }'];
        }

        $lines = ['    '.$entity->id.' {'];
        foreach ($entity->attributes as $attribute) {
            $lines[] = '        '.$this->attributeLine($attribute);
        }
        $lines[] = '    }';

        return $lines;
    }

    private function attributeLine(ErAttribute $attribute): string
    {
        $this->assertSerializableToken($attribute->type, 'attribute type');
        $this->assertSerializableId($attribute->name, 'attribute name');

        return $attribute->type.' '.$attribute->name;
    }

    private function relationshipLine(ErRelationship $relationship): string
    {
        $this->assertSerializableId($relationship->from, 'relationship source id');
        $this->assertSerializableId($relationship->to, 'relationship target id');

        $line = \sprintf('%s %s %s', $relationship->from, $relationship->edgeToken(), $relationship->to);
        if (null === $relationship->label) {
            return $line;
        }

        $this->assertSerializableText($relationship->label->text, \sprintf('label of relationship "%s" to "%s"', $relationship->from, $relationship->to));

        return $line.' : '.$relationship->label->text;
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'ER diagram', $what);
    }

    private function assertSerializableToken(string $token, string $what): void
    {
        MermaidSerializable::token($token, 'ER diagram', $what);
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'ER diagram', $what, '/[\r\n]/', 'contains unsupported control characters');
    }
}
