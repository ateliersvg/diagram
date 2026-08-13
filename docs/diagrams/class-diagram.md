---
order: 40
title: Class
---
# Class Diagrams

A class diagram is a set of named class boxes, each holding member rows, plus the directed relations between them.

<img src="../images/class-diagram.svg" alt="A class diagram with members and relations">

- [Overview](#overview)
- [Format](#format)
- [Builder API](#builder-api)
- [Options](#options)
- [Themes](#themes)
- [Parse](#parse)
- [Debug](#debug)

## Overview

The model lives in `Atelier\Diagram\ClassDiagram\`: `ClassDiagram` (the class boxes and relations), `ClassBox` (`id` plus its member rows), `ClassMember` (a single `text` row), and `ClassRelation` (`from`, `to`, optional label). All model classes are immutable.

Reach for it to show the shape of a type model: the classes in a domain, the fields and operations on each, and how they reference one another. Use an [ER diagram](er-diagram.md) instead when you are modeling database entities and their keyed relationships rather than code-level types.

## Format

Class diagrams round-trip through a `classDiagram` subset:

```mermaid
classDiagram
    class User
    User : +id int
    class Order
    User --> Order
    User --> Order : places
```

Class ids match `[A-Za-z_][A-Za-z0-9_]*`. Classes referenced by members or relations are auto-declared in first-use order. Inheritance arrows, class blocks, annotations, namespaces, generics, structured method parsing, and styling are rejected, never skipped. Full grammar: [Mermaid support](../mermaid.md#class-diagram-subset).

## Builder API

`ClassDiagramBuilder` (or `Diagram::classDiagram()`, which returns it) is the one way to build the model:

```php
use Atelier\Diagram\Diagram;

$diagram = Diagram::classDiagram()
    ->class('User')                             // declare a class
    ->member('User', '+id int')                 // add a member row
    ->member('User', '+email string')
    ->member('Order', '+total Money')           // auto-declares Order
    ->relation('User', 'Order', 'places')       // labeled directed relation
    ->build();

Diagram::of($diagram)->saveSvg('classes.svg');
```

- `class($id)` - declares a class. Declaring an existing id again is a no-op. An empty id throws `InvalidArgumentException`.
- `member($classId, $text)` - appends a member row and auto-declares the class if unseen. Declaration order is the layout tie-breaker.
- `relation($from, $to, $label)` - adds a directed relation and auto-declares unknown endpoints. `$label` is optional.
- `build()` - assembles the classes in declaration order; returns a `ClassDiagram`.

## Options

| Setting | Default | Effect |
|---|---|---|
| `member(...)` | none | one row under the class name; a class with none renders as a bare titled box |
| `Theme::minNodeWidth` / `minNodeHeight` | `null` | floor for boxes, which otherwise fit their widest row |

- **Determinism**: layout is source-order based and deterministic; the same model always yields the same SVG.

`Layout\ClassDiagram\ClassDiagramLayoutEngine` places class boxes in a source-order grid and routes relations with `atelier/layout` orthogonal ports. Class names and member rows are bounded and wrapped, so long labels do not force unusable boxes. Relation labels use the shared route-label treatment: centered on the route, wrapped when needed, and drawn over a light background so the edge stays readable. Graph ranking, inheritance-specific arrowheads, compartments beyond member rows, and full relation collision solving are later work.

## Themes

Class diagrams use `nodeFillColor` and `nodeStrokeColor` for the boxes and their row separators, `nodeStrokeColor` for relation edges and arrowheads, and `textColor` for class names and member rows. They do **not** use `accentColors` (every box is styled uniformly), so the palette size does not matter here.

All presets apply (`Theme::default()`, `dark()`, `blueprint()`, `mono()`, `neutral()`), and a `Scene\BackgroundPattern` (as in `blueprint()`) sits behind the boxes. See [Theming](../theming.md).

<img src="../images/class-diagram-theme.svg" alt="The same class diagram in the blueprint preset">

`Theme::blueprint()`: deep navy field, cyan strokes, thin lines. The model is untouched; only the theme passed to `toSvg()` changed.

## Parse

Header keyword: `classDiagram`.

```php
$diagram = Diagram::fromMermaid($source);        // throws ParseException
$result  = Diagram::tryFromMermaid($source);      // non-throwing ParseResult
```

`toMermaid()` / `toMarkdown()` serialize a built model back. Full grammar and round-trip rules: [Mermaid support](../mermaid.md#class-diagram-subset).

## Debug

On a rejected source, inspect `ParseResult::getDiagnostic()` (a `ParserDiagnostic` with a message, a stable `code`, and a source span) or catch `ParseException` (`getLineNumber()`, `getSourceLine()`). Render a code frame with `ParserDiagnosticFormatter`:

```php
$result = Diagram::tryFromMermaid($source);
if ($result->isFailure()) {
    echo \Atelier\Diagram\Parser\Support\ParserDiagnosticFormatter::format($result->getDiagnostic());
}
```

See [parser diagnostics](../mermaid.md#debugging).
