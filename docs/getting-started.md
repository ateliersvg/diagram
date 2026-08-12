---
order: 10
---
# Getting Started

`atelier/diagram` has two inputs and two outputs:

- Input: fluent PHP builders or Mermaid text.
- Output: SVG geometry or canonical Mermaid markdown.

## Diagram types

One page per type, each with the same sections (overview, format, builder API, options, themes, parse, debug, examples):

- [State](diagrams/state-diagram.md)
- [Venn](diagrams/venn-diagram.md)
- [Git graph](diagrams/git-graph.md)
- [Sequence](diagrams/sequence-diagram.md)
- [Flowchart](diagrams/flowchart.md)
- [Class](diagrams/class-diagram.md)
- [ER](diagrams/er-diagram.md)
- [Timeline](diagrams/timeline-diagram.md)
- [Journey](diagrams/journey-diagram.md)
- [Mindmap](diagrams/mindmap-diagram.md)
- [Requirement](diagrams/requirement-diagram.md)
- [Kanban](diagrams/kanban-diagram.md)
- [Block](diagrams/block-diagram.md)
- [Architecture](diagrams/architecture-diagram.md)
- [C4](diagrams/c4-diagram.md)

## Install

```bash
composer require atelier/diagram
```

## Build With PHP

```php
use Atelier\Diagram\Diagram;

$diagram = Diagram::flowchart()
    ->node('Cart', 'Cart')
    ->edge('Cart', 'Payment', 'checkout')
    ->node('Payment', 'Payment')
    ->build();

echo Diagram::of($diagram)->toSvg();
```

Builder entry points:

- `Diagram::state()`
- `Diagram::venn()`
- `Diagram::git()`
- `Diagram::sequence()`
- `Diagram::flowchart()`
- `Diagram::classDiagram()`
- `Diagram::er()`
- `Diagram::timeline()`
- `Diagram::journey()`
- `Diagram::mindmap()`
- `Diagram::requirement()`
- `Diagram::kanban()`
- `Diagram::block()`
- `Diagram::architecture()`
- `Diagram::c4()`

Each builder produces a typed immutable model. `Diagram::of($model)` wraps the model for rendering.

## Parse Mermaid

```php
$diagram = Diagram::fromMermaid(<<<'MERMAID'
    flowchart TD
        Cart[Cart]
        Cart -->|checkout| Payment
        Payment[Payment]
    MERMAID);

$svg = $diagram->toSvg();
$canonical = $diagram->toMermaid();
```

Supported Mermaid headers:

- `stateDiagram-v2` / `stateDiagram`
- `gitGraph`
- `sequenceDiagram`
- `flowchart TD` / `flowchart TB` / `flowchart LR`
- `classDiagram`
- `erDiagram`
- `timeline`
- `journey`
- `mindmap`
- `requirementDiagram`
- `kanban`
- `block`
- `architecture`
- `C4Context` / `C4Container` / `C4Component`

Venn diagrams currently have no text grammar in this package.

## Output

```php
$wrapped = Diagram::of($diagram);

$wrapped->toSvg();          // string
$wrapped->toSvgDocument();  // Atelier\Svg\Document
$wrapped->saveSvg('x.svg');
$wrapped->toMermaid();      // raw Mermaid, when supported
$wrapped->toMarkdown();     // fenced mermaid block, when supported
```

## Error Handling

Parsers are strict. Unsupported syntax throws `ParseException` with a 1-based source line:

```php
use Atelier\Diagram\Exception\ParseException;

try {
    Diagram::fromMermaid($source);
} catch (ParseException $e) {
    $e->getLineNumber();
    $e->getSourceLine();
}
```

## Verify Locally

```bash
composer qa
php examples/demo.php
php examples/gallery.php
```

The demo command writes `examples/output/index.html`, with links to the gallery, showcase, label-polish audit, and effects demo. The gallery writes SVG and Mermaid artifacts to `examples/output/`.
