<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Renderer\Markdown\MermaidSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MermaidSerializable::class)]
final class MermaidSerializableTest extends TestCase
{
    public function testStrictIdRejectsUnsupportedIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render flowchart to Mermaid: node id "Bad Id" is not a supported identifier.');

        MermaidSerializable::strictId('Bad Id', 'flowchart', 'node id');
    }

    public function testFieldNameRejectsUnsupportedIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render requirement diagram to Mermaid: field name "bad-name" is not a supported identifier.');

        MermaidSerializable::fieldName('bad-name', 'requirement diagram');
    }

    public function testTokenRejectsWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render ER diagram to Mermaid: attribute type "var char" is not a supported token.');

        MermaidSerializable::token('var char', 'ER diagram', 'attribute type');
    }

    public function testTextRejectsUnsupportedCharactersAndEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render block diagram to Mermaid: block label contains unsupported control characters.');

        MermaidSerializable::text('Bad]', 'block diagram', 'block label', '/[\r\n\]]/', 'contains unsupported control characters');
    }

    public function testTextRejectsEmptyTextAfterTrim(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render block diagram to Mermaid: block label must not be empty.');

        MermaidSerializable::text('   ', 'block diagram', 'block label', '/[\r\n\]]/', 'contains unsupported control characters');
    }
}
