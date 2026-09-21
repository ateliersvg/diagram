---
order: 10
description: Install the package, build a diagram with the fluent PHP builders or from Mermaid text, then render it as SVG or back to Mermaid.
---
# Getting Started

`atelier/diagram` has two inputs and two outputs:

- Input: fluent PHP builders or Mermaid text.
- Output: SVG geometry or canonical Mermaid markdown.

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

Flowcharts accept the `TD`, `TB`, and `LR` directions. Venn diagrams have no text grammar in this package.

## Diagram types

Each type has the same documentation sections: overview, format, builder API, options, themes, parse, debug, and examples.

| Diagram | Builder | Mermaid |
|---|---|---|
| [State](diagrams/state-diagram.md) | `Diagram::state()` | `stateDiagram-v2` / `stateDiagram` |
| [Venn](diagrams/venn-diagram.md) | `Diagram::venn()` | |
| [Git graph](diagrams/git-graph.md) | `Diagram::git()` | `gitGraph` |
| [Sequence](diagrams/sequence-diagram.md) | `Diagram::sequence()` | `sequenceDiagram` |
| [Flowchart](diagrams/flowchart.md) | `Diagram::flowchart()` | `flowchart` |
| [Class](diagrams/class-diagram.md) | `Diagram::classDiagram()` | `classDiagram` |
| [ER](diagrams/er-diagram.md) | `Diagram::er()` | `erDiagram` |
| [Timeline](diagrams/timeline-diagram.md) | `Diagram::timeline()` | `timeline` |
| [Journey](diagrams/journey-diagram.md) | `Diagram::journey()` | `journey` |
| [Mindmap](diagrams/mindmap-diagram.md) | `Diagram::mindmap()` | `mindmap` |
| [Requirement](diagrams/requirement-diagram.md) | `Diagram::requirement()` | `requirementDiagram` |
| [Kanban](diagrams/kanban-diagram.md) | `Diagram::kanban()` | `kanban` |
| [Block](diagrams/block-diagram.md) | `Diagram::block()` | `block` |
| [Architecture](diagrams/architecture-diagram.md) | `Diagram::architecture()` | `architecture` |
| [C4](diagrams/c4-diagram.md) | `Diagram::c4()` | `C4Context` / `C4Container` / `C4Component` |

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
