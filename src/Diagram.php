<?php

declare(strict_types=1);

namespace Atelier\Diagram;

use Atelier\Diagram\Architecture\ArchitectureDiagramBuilder;
use Atelier\Diagram\Block\BlockDiagramBuilder;
use Atelier\Diagram\C4\C4DiagramBuilder;
use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;
use Atelier\Diagram\Er\ErDiagramBuilder;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Exception\RuntimeException;
use Atelier\Diagram\Flow\FlowchartBuilder;
use Atelier\Diagram\Git\GitGraphBuilder;
use Atelier\Diagram\Journey\JourneyDiagramBuilder;
use Atelier\Diagram\Kanban\KanbanDiagramBuilder;
use Atelier\Diagram\Layout\LayoutRegistry;
use Atelier\Diagram\Mindmap\MindmapDiagramBuilder;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Parser\Support\ParseResult;
use Atelier\Diagram\Parser\Support\ParserInputLimits;
use Atelier\Diagram\Renderer\Markdown\MarkdownRenderer;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Requirement\RequirementDiagramBuilder;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;
use Atelier\Diagram\State\StateDiagramBuilder;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;
use Atelier\Diagram\Venn\VennDiagramBuilder;
use Atelier\Svg\Document;

/**
 * Facade: one obvious line from a diagram idea to its output.
 *
 * Mirrors the pipeline's two entry points. The builder factories
 * (state(), venn(), git(), sequence(), classDiagram(), er(), timeline(), mindmap(), requirement(), kanban(), block(), architecture(), c4()) return the fluent builders unchanged; the
 * facade adds nothing on top of them; build the model, then wrap it with
 * Diagram::of(). fromMermaid() parses text and wraps the model directly.
 * A wrapped diagram exits through toSvg() / toSvgDocument() / saveSvg()
 * (geometric) and toMarkdown() / toMermaid() (textual, Mermaid-backed diagrams only).
 *
 * Engine dispatch is a match on the model class -- the documented reason
 * there is no LayoutEngineInterface. Advanced users compose the
 * underlying classes (engines, renderers, parser) directly.
 *
 * ```php
 * Diagram::of(
 *     Diagram::state()
 *         ->initial('Draft')
 *         ->transition('Draft', 'Done', 'finish')
 *         ->final('Done')
 *         ->build(),
 * )->saveSvg('diagram.svg');
 *
 * $markdown = Diagram::fromMermaid($mermaidSource)->toMarkdown();
 * ```
 */
final class Diagram
{
    private function __construct(
        private readonly DiagramModel $model,
    ) {
    }

    /**
     * Starts a state diagram; finish with ->build() and Diagram::of().
     */
    public static function state(): StateDiagramBuilder
    {
        return new StateDiagramBuilder();
    }

    /**
     * Starts a Venn diagram; finish with ->build() and Diagram::of().
     */
    public static function venn(): VennDiagramBuilder
    {
        return new VennDiagramBuilder();
    }

    /**
     * Starts a git graph; finish with ->build() and Diagram::of().
     */
    public static function git(): GitGraphBuilder
    {
        return new GitGraphBuilder();
    }

    /**
     * Starts a sequence diagram; finish with ->build() and Diagram::of().
     */
    public static function sequence(): SequenceDiagramBuilder
    {
        return new SequenceDiagramBuilder();
    }

    /**
     * Starts a flowchart; finish with ->build() and Diagram::of().
     */
    public static function flowchart(): FlowchartBuilder
    {
        return new FlowchartBuilder();
    }

    /**
     * Starts a class diagram; finish with ->build() and Diagram::of().
     */
    public static function classDiagram(): ClassDiagramBuilder
    {
        return new ClassDiagramBuilder();
    }

    /**
     * Starts an ER diagram; finish with ->build() and Diagram::of().
     */
    public static function er(): ErDiagramBuilder
    {
        return new ErDiagramBuilder();
    }

    /**
     * Starts a timeline diagram; finish with ->build() and Diagram::of().
     */
    public static function timeline(): TimelineDiagramBuilder
    {
        return new TimelineDiagramBuilder();
    }

    /**
     * Starts a user journey diagram; finish with ->build() and Diagram::of().
     */
    public static function journey(): JourneyDiagramBuilder
    {
        return new JourneyDiagramBuilder();
    }

    /**
     * Starts a mindmap; finish with ->build() and Diagram::of().
     */
    public static function mindmap(): MindmapDiagramBuilder
    {
        return new MindmapDiagramBuilder();
    }

    /**
     * Starts a requirement diagram; finish with ->build() and Diagram::of().
     */
    public static function requirement(): RequirementDiagramBuilder
    {
        return new RequirementDiagramBuilder();
    }

    /**
     * Starts a kanban board diagram; finish with ->build() and Diagram::of().
     */
    public static function kanban(): KanbanDiagramBuilder
    {
        return new KanbanDiagramBuilder();
    }

    /**
     * Starts a block diagram; finish with ->build() and Diagram::of().
     */
    public static function block(): BlockDiagramBuilder
    {
        return new BlockDiagramBuilder();
    }

    /**
     * Starts an architecture diagram; finish with ->build() and Diagram::of().
     */
    public static function architecture(): ArchitectureDiagramBuilder
    {
        return new ArchitectureDiagramBuilder();
    }

    /**
     * Starts a C4 diagram; finish with ->build() and Diagram::of().
     */
    public static function c4(): C4DiagramBuilder
    {
        return new C4DiagramBuilder();
    }

    /**
     * Wraps a built model.
     */
    public static function of(DiagramModel $model): self
    {
        return new self($model);
    }

    /**
     * Parses Mermaid source (stateDiagram-v2, gitGraph, sequenceDiagram, flowchart, classDiagram, erDiagram, timeline, journey, mindmap, requirementDiagram, kanban, block, architecture or C4) and wraps the model.
     *
     * @throws ParseException
     */
    public static function fromMermaid(string $source, ?ParserInputLimits $limits = null): self
    {
        return new self((new MermaidParser(limits: $limits))->parse($source));
    }

    public static function tryFromMermaid(string $source, ?ParserInputLimits $limits = null): ParseResult
    {
        try {
            return ParseResult::success(self::fromMermaid($source, $limits));
        } catch (ParseException $exception) {
            return ParseResult::failure($exception);
        }
    }

    /**
     * The wrapped model -- the escape hatch to the full API.
     */
    public function getModel(): DiagramModel
    {
        return $this->model;
    }

    /**
     * Renders to compact SVG markup.
     */
    public function toSvg(?Theme $theme = null): string
    {
        return (new SvgRenderer())->render($this->layout($theme));
    }

    /**
     * Renders to an Atelier\Svg\Document, for downstream processing
     * (optimizer, sanitizer) through atelier/svg.
     */
    public function toSvgDocument(?Theme $theme = null): Document
    {
        return (new SvgRenderer())->renderToDocument($this->layout($theme));
    }

    /**
     * Renders to SVG and writes it to a file.
     *
     * @throws RuntimeException if the file cannot be written
     */
    public function saveSvg(string $path, ?Theme $theme = null): self
    {
        if (false === @file_put_contents($path, $this->toSvg($theme))) {
            throw new RuntimeException(\sprintf('Failed to write SVG to file: %s', $path));
        }

        return $this;
    }

    /**
     * Renders to a ```mermaid fenced markdown block (except Venn, which has no Mermaid grammar).
     *
     * @throws InvalidArgumentException for Venn diagrams (no Mermaid grammar)
     */
    public function toMarkdown(): string
    {
        return (new MarkdownRenderer())->render($this->model);
    }

    /**
     * Renders to raw canonical Mermaid source (except Venn, which has no Mermaid grammar).
     *
     * @throws InvalidArgumentException for Venn diagrams (no Mermaid grammar)
     */
    public function toMermaid(): string
    {
        return (new MarkdownRenderer())->renderMermaid($this->model);
    }

    private function layout(?Theme $theme): Scene
    {
        $model = $this->model;
        $theme ??= Theme::default();

        $scene = LayoutRegistry::default()->layout($model, $theme);

        if (null !== $theme->backgroundPattern) {
            $scene = $scene->withBackgroundPattern($theme->backgroundPattern);
        }

        return $scene;
    }
}
