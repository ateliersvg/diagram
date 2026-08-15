---
order: 40
---
# Renderers

Two exits, two renderers. `Renderer\Svg\SvgRenderer` draws the geometric form: it renders the `Scene` a layout engine produced. `Renderer\Markdown\MarkdownRenderer` writes the textual form: it serializes Mermaid-backed models back to Mermaid source. The `Diagram` facade fronts both.

## SVG

The facade exits cover the common cases:

```php
$diagram = Diagram::of($model);

$svg = $diagram->toSvg();              // compact SVG markup
$diagram->saveSvg('diagram.svg');      // same, written to a file
$document = $diagram->toSvgDocument(); // Atelier\Svg\Document
```

All three accept an optional `Theme` (see [Theming](theming.md)). `saveSvg()` throws `RuntimeException` when the file cannot be written.

`SvgRenderer` itself maps Scene nodes 1:1 to SVG elements: rects, circles, lines, paths, text, and groups. Coordinates are rounded to 2 decimals. `ShapeStyle` controls stroke width, dash pattern, line caps, and line joins without exposing SVG types to the layout layer. `TextStyle` carries font family, size, anchor, fill, and `Atelier\Layout\Text\FontWeight`, matching the shared text measurement layer used by layout engines. No `<marker>` elements: arrowheads arrive from layout as filled paths, so the output works in any SVG consumer.

The root SVG carries `class="atelier-diagram"` and `data-renderer="atelier/diagram"` so downstream apps can target generated diagrams without parsing internal shapes. When a `Scene` has a title or description, the renderer emits `<title>`, `<desc>`, and `role="img"`.

### Post-processing with atelier/svg

`toSvgDocument()` (or `SvgRenderer::renderToDocument()`) returns an `Atelier\Svg\Document`, the entry point to the whole `atelier/svg` toolbox: optimizer, sanitizer, validator.

```php
use Atelier\Svg\Dumper\CompactXmlDumper;
use Atelier\Svg\Optimizer\Optimizer;
use Atelier\Svg\Optimizer\OptimizerPresets;

$document = Diagram::of($model)->toSvgDocument();

(new Optimizer(OptimizerPresets::web()))->optimize($document);

file_put_contents('diagram.svg', (new CompactXmlDumper())->dump($document));
```

## Markdown / Mermaid

`MarkdownRenderer` is the inverse of the parser. It renders a **model**, not a Scene:

```php
$diagram = Diagram::of($stateDiagram);

$markdown = $diagram->toMarkdown();  // ```mermaid fenced block
$mermaid = $diagram->toMermaid();    // raw Mermaid source
```

Or directly: `(new MarkdownRenderer())->render($model)` / `->renderMermaid($model)`.

### Canonical output

The output is canonical and deterministic: header first, one statement per line indented with 4 spaces, statements in declaration/operation order, no trailing whitespace. It normalizes equivalent spellings: `A : description` becomes `state "description" as A`, `switch` becomes `checkout`, git commit attributes always appear as `id` then `tag`, and auto-declared sequence/flowchart nodes are emitted explicitly. Rendering, parsing, and rendering again yields the same string.

For git graphs the model stores branches and commits, not the original statement sequence, so the serializer reconstructs one by replaying the history. Commit ids that match the builder's auto-id sequence are omitted, so re-parsing regenerates them identically.

### What is lost

The supported state grammar has no statement for diagram titles, and the git grammar has no statement for the legend flag, so those are dropped silently. The serialized text re-parses to the same diagram minus that setting. Sequence, flowchart, and C4 titles are serialized. Everything the grammar can express round-trips exactly; see [Mermaid support](mermaid.md) for the guarantees.

Models built directly, bypassing the builders, can be unrepresentable: a state id with a space, a git history not starting on `main`, a tagged merge commit, or labels containing Mermaid control characters. Those throw `InvalidArgumentException` with a message naming the offending element.

### Venn

Venn has no text grammar in this package: `toMarkdown()`, `toMermaid()` and `MarkdownRenderer` reject `VennDiagram` with an `InvalidArgumentException`.

## Below the facade

Advanced callers compose the pieces directly: a layout engine produces a `Scene`, any `Renderer\RendererInterface` consumes it.

```php
use Atelier\Diagram\Layout\State\StateLayoutEngine;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Theme\Theme;

$scene = (new StateLayoutEngine())->layout($stateDiagram, Theme::default());
$svg = (new SvgRenderer())->render($scene);
```

The Scene is renderer-agnostic: positioned primitives, plain string colors, no SVG types. Other renderers can be added without touching layout.
