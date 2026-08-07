<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Venn\VennDiagram;

/**
 * Renders a diagram model back to Mermaid source -- the inverse of Parser/.
 *
 * Output is canonical and deterministic: header first, direction only when
 * non-default, one statement per line indented with 4 spaces, statements
 * in declaration/operation order, no trailing whitespace. Rendering, then
 * parsing, then rendering again is the identity, and the re-parsed model
 * lays out identically to the original.
 *
 * State titles and the git legend flag are not part of the supported grammar
 * and are therefore not serialized. Sequence titles are serialized. Venn
 * diagrams have no Mermaid grammar at all and are rejected with an
 * InvalidArgumentException.
 *
 * Deliberately not a RendererInterface implementation: it renders a model,
 * not a Scene.
 */
final class MarkdownRenderer
{
    private readonly MermaidSerializerRegistry $serializers;

    public function __construct(?MermaidSerializerRegistry $serializers = null)
    {
        $this->serializers = $serializers ?? MermaidSerializerRegistry::default();
    }

    /**
     * Renders the model as a markdown ```mermaid fenced code block.
     */
    public function render(DiagramModel $diagram): string
    {
        return "```mermaid\n".$this->renderMermaid($diagram)."```\n";
    }

    /**
     * Renders the model as raw canonical Mermaid source.
     */
    public function renderMermaid(DiagramModel $diagram): string
    {
        if ($diagram instanceof VennDiagram) {
            throw new InvalidArgumentException('Venn diagrams cannot be rendered to Mermaid: the Mermaid language has no Venn grammar.');
        }

        return $this->serializers->serialize($diagram);
    }
}
