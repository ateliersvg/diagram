---
order: 70
title: Venn
---
# Venn Diagrams

A Venn diagram is 2 or 3 overlapping sets with an optional title, optional region labels, and an optional legend.

<figure class="diagram-intro">
<img src="../images/venn-diagram.svg" alt="Three overlapping sets with labelled regions">
<figcaption>Three overlapping sets with labels for each region.</figcaption>
</figure>

- [Overview](#overview)
- [Format](#format)
- [Builder API](#builder-api)
- [Options](#options)
- [Themes](#themes)
- [Parse](#parse)
- [Debug](#debug)

## Overview

The model lives in `Atelier\Diagram\Venn\`: `VennDiagram` and `VennSet` (`id`, `label`, optional `cardinality`). All model classes are immutable.

Sets receive the ids `A`, `B`, `C` in declaration order. Regions are addressed by the ids of the sets they intersect: `A`, `B`, `C` for the exclusive regions, `AB`, `AC`, `BC` for the pairwise intersections, `ABC` for the center.

Reach for it to show membership overlap between a small number of categories: skills shared across roles, features across plans, tags across documents. Cardinalities are carried by the model but the schematic layout does not size regions by them.

## Format

This type has no Mermaid grammar; it is built through the PHP builder only. See [Parse](#parse).

## Builder API

`VennDiagramBuilder` (or `Diagram::venn()`, which returns it) is the one way to build the model:

```php
use Atelier\Diagram\Diagram;

$diagram = Diagram::venn()
    ->title('Web skills')
    ->set('Frontend', 42)        // becomes set A
    ->set('Backend', 35)         // becomes set B
    ->regionLabel('A', 'CSS')    // Frontend only
    ->regionLabel('B', 'SQL')    // Backend only
    ->regionLabel('AB', 'HTTP')  // intersection
    ->withLegend()
    ->build();

Diagram::of($diagram)->saveSvg('skills.svg');
```

`set()`
: Adds set A, B, then C. A fourth set throws; cardinality is optional and does not affect schematic layout.

  | Argument | Type | Description |
  |---|---|---|
  | `$label` | `string` | Set label. |
  | `$cardinality` | `?int` | Optional displayed cardinality. |

`regionLabel()`
: Labels a region; the last call wins. Invalid regions throw immediately, while references to undeclared sets are checked at `build()`.

  | Argument | Type | Description |
  |---|---|---|
  | `$region` | `string` | `A`, `B`, `C`, `AB`, `AC`, `BC`, or `ABC`. |
  | `$text` | `string` | Region label. |

`title()`
: Sets a title. Venn has no Mermaid text form.

  | Argument | Type | Description |
  |---|---|---|
  | `$title` | `string` | Non-empty title. |

`withLegend()`
: Enables the set-to-color legend, which is off by default.

`targetSize()`
: Constrains the schematic to an exact canvas.

  | Argument | Type | Description |
  |---|---|---|
  | `$width` | `float` | Canvas width. |
  | `$height` | `float` | Canvas height. |

`paddingPercent()` / `innerPaddingPercent()`
: Sets outer canvas padding or label padding inside each circle's safe area.

  | Argument | Type | Description |
  |---|---|---|
  | `$percent` | `float` | Padding as a percentage. |

`circleStrokeWidth()`
: Sets the contained circle stroke width.

  | Argument | Type | Description |
  |---|---|---|
  | `$strokeWidth` | `float` | Stroke width in SVG units. |

`build()`
: Assembles the immutable `VennDiagram`. Fewer than two sets or labels that reference undeclared sets throw.

## Options

| Setting | Default | Effect |
|---|---|---|
| `withLegend()` | no legend | set-to-colour legend at the bottom left |
| `title(string)` | none | heading above the schematic |
| `targetSize(float, float)` | unset | fixes the scene width and height instead of sizing to the geometry |
| `paddingPercent(float)` | `0` | canvas padding around the schematic |
| `innerPaddingPercent(float)` | `0` | label padding inside each circle safe area |
| `circleStrokeWidth(float)` | `0` | fixed circle stroke width |

- **Determinism**: layout is source-order based and deterministic; the same model always yields the same SVG.

`Layout\Venn\VennLayoutEngine` uses fixed schematic geometry: 2 sets as equal circles side by side, 3 sets on an equilateral triangle. Set labels sit outside the circles, region labels at approximate region centroids. The radius grows automatically when a region label is wider than the default circle.

<img src="../images/venn-diagram-two-sets.svg" alt="Two overlapping sets with labelled regions">

Two sets instead of three. The layout engine places circles and region labels from the set count, so a Venn diagram is never hand-positioned.

## Themes

Venn diagrams fill the circles with `accentColors` at 0.5 opacity so intersections stay readable; the same palette drives the legend swatches. They also use `nodeStrokeColor` for circle strokes, `textColor` for set and region labels, and `fontFamily` / `fontSize` for text. A palette of at least 2 (3 for a triple diagram) distinct accent colors reads best; colors are cycled by set index.

All presets apply (`Theme::default()`, `dark()`, `blueprint()`, `mono()`, `neutral()`). See [Theming](../theming.md).

## Parse

Not applicable: this type has no Mermaid grammar, so `toMermaid()` / `toMarkdown()` throw and `Diagram::fromMermaid()` never returns one. SVG output works like for any other type.

## Debug

The builder throws `InvalidDiagramException` on invalid input (a fourth set, an unknown region key, a region referencing an undeclared set, or fewer than 2 sets). There is no parser, so no parser diagnostics apply.
