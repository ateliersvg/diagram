---
order: 50
title: Mermaid
description: Parse and emit canonical Mermaid for fifteen diagram grammars. Each supported subset is small, exactly specified, and symmetric.
---
# Mermaid Support

`atelier/diagram` parses Mermaid-like grammars including `stateDiagram-v2`, `gitGraph`, `sequenceDiagram`, `flowchart`, `classDiagram`, `erDiagram`, `timeline`, `journey`, `mindmap`, `requirementDiagram`, `kanban`, `block`, `architecture`, `C4Context`, `C4Container`, and `C4Component`. The supported subsets are deliberately small, exactly specified, and symmetric: everything the parser accepts can be emitted back by the [markdown renderer](renderers.md). Venn diagrams have no text form in this package. Each subset below builds the model its own [diagram type page](diagrams/overview.md) documents.

## Parsing

```php
use Atelier\Diagram\Diagram;

$diagram = Diagram::fromMermaid($source);   // grammar auto-detected
$svg = $diagram->toSvg();
```

`Diagram::fromMermaid()` (backed by `Parser\MermaidParser`) dispatches on the first significant line through `Parser\MermaidGrammarRegistry`: `stateDiagram-v2` or `stateDiagram` selects the state parser, `gitGraph` the git parser, `sequenceDiagram` the sequence parser, `flowchart` the flowchart parser, `classDiagram` the class parser, `erDiagram` the ER parser, `timeline` the timeline parser, `journey` the journey parser, `mindmap` the mindmap parser, `requirementDiagram` the requirement parser, `kanban` the kanban parser, `block` the block parser, `architecture` the architecture parser, and `C4Context`, `C4Container`, or `C4Component` the C4 parser. Anything else throws a `ParseException` at line 1. All grammars skip blank lines and `%%` comment lines. Most grammars ignore indentation; `mindmap` and `kanban` use strict indentation because indentation is part of their structure.

Every grammar accepts only the statements documented below. Unsupported syntax raises a `ParseException` instead of being skipped, and supported models serialize back to canonical Mermaid.

## State diagram subset

Header: `stateDiagram-v2` (or `stateDiagram`). Then, in any order:

| Statement | Meaning |
|---|---|
| `direction TB` / `direction LR` | flow direction (TB is the default) |
| `A --> B` | transition; endpoints are auto-declared |
| `A --> B : label` | labeled transition (label must be non-empty) |
| `[*] --> A` | transition from the initial pseudo-state |
| `A --> [*]` | transition to the final pseudo-state |
| `state "Long label" as A` | declare a state with a display label |
| `A : description` | set/override the label of A |

State ids match `[A-Za-z_][A-Za-z0-9_]*`. Everything else is rejected, never skipped: composite `state { }` blocks, forks, joins, notes, concurrency, and `-->` annotations beyond a plain label.

## Git graph subset

Header: `gitGraph`, optionally suffixed `LR:` or `TB:` (LR is the default). Then:

| Statement | Meaning |
|---|---|
| `commit` | commit on the current branch, auto-generated id |
| `commit id: "x"` | commit with an explicit id |
| `commit tag: "v1"` | commit with a tag (`id` and `tag` combine, in either order, each at most once) |
| `branch <name>` | create a branch at the current tip and check it out |
| `checkout <name>` / `switch <name>` | switch the current branch |
| `merge <name>` | merge the named branch into the current one |

Branch names are whitespace-free tokens. Everything else is rejected: `cherry-pick`, `commit type:`, `order:`, and options blocks.

## Sequence diagram subset

Header: `sequenceDiagram`. Then:

| Statement | Meaning |
|---|---|
| `title Text` | optional title |
| `participant A` | declare participant A |
| `participant A as Alice` | declare participant A with display label Alice |
| `A->>B: label` | solid message; endpoints are auto-declared |
| `A-->>B: label` | dashed reply-style message |
| `loop Label` ... `end` | non-nested block spanning messages |
| `alt Label` ... `end` | non-nested alternative block spanning messages |
| `else Label` | branch separator inside `alt` |
| `opt Label` ... `end` | optional block |
| `par Label` / `and Label` ... `end` | parallel branch block |
| `activate A` / `deactivate A` | activation span on a participant lifeline |

Participant ids match `[A-Za-z_][A-Za-z0-9_]*`. Everything else is rejected: actors, notes, nested blocks, autonumber, and destruction markers.

## Flowchart subset

Header: `flowchart TD`, `flowchart TB`, or `flowchart LR`. Then:

| Statement | Meaning |
|---|---|
| `title Text` | optional title |
| `A[Label]` | rectangular node declaration |
| `A --> B` | directed edge; endpoints are auto-declared |
| `A -->|label| B` | labeled directed edge |
| `subgraph id [Label]` ... `end` | node cluster; nesting is supported |

Node ids match `[A-Za-z_][A-Za-z0-9_]*`. Everything else is rejected: alternate shapes, edge variants, classes, and markdown labels.

## Class diagram subset

Header: `classDiagram`. Then:

| Statement | Meaning |
|---|---|
| `class User` | declare a class |
| `User : +id int` | add a member row; class is auto-declared if needed |
| `User --> Order` | directed relation; endpoints are auto-declared |
| `User --> Order : places` | labeled directed relation |

Class ids match `[A-Za-z_][A-Za-z0-9_]*`. The supported subset does not include inheritance arrows, visibility parsing, methods as structured members, annotations, namespaces, or class blocks.

## ER diagram subset

Header: `erDiagram`. Then:

| Statement | Meaning |
|---|---|
| `CUSTOMER \|\|--o{ ORDER : places` | relationship with a cardinality on each side and an optional label |
| `ORDER {` ... `}` | entity block |
| `int id` | attribute row (`type name`) inside an entity block |

Cardinality tokens are `\|o` (zero or one), `\|\|` (exactly one), `o{` (zero or more), and `\|{` (one or more); the pair is written `<left>--<right>`. Entity ids match `[A-Za-z_][A-Za-z0-9_]*`. The supported subset does not include identifying/non-identifying styling, attribute keys and comments, quoted labels, or alias blocks.

## Timeline subset

Header: `timeline`. Then:

| Statement | Meaning |
|---|---|
| `title Product launch` | optional title |
| `section Discovery` | starts a named horizontal lane |
| `Research complete : 2026-01` | event label and date label in the current section |

Events must belong to an explicit section. Date values are free-text labels: they are not parsed, sorted, scaled, or treated as durations. The supported subset does not include calendar scaling, range bars, nested phases, styling, or date arithmetic.

## Journey diagram subset

Header: `journey`. Then:

| Statement | Meaning |
|---|---|
| `title Text` | optional title |
| `section Browse` | start a section lane |
| `Open product page: 5: Customer` | task with score and actor |
| `Enter card: 3: Customer, PSP` | task with multiple actors |

Scores must be integers from 1 to 5. Tasks must belong to a section and must declare at least one actor. The layout renders score as a badge/intensity, not as a chart axis.

## Mindmap subset

Header: `mindmap`. Then:

| Statement | Meaning |
|---|---|
| `root((Atelier))` | declare the single root node |
| `  Layout` | child of the previous shallower node |
| `    Grid` | grandchild, two spaces deeper |

The parser requires spaces only, exactly two spaces per level, one root, and no skipped indentation levels. The supported subset does not include icons, classes, markdown labels, arbitrary shapes, or multiple roots.

## Requirement diagram subset

Header: `requirementDiagram`. Then:

| Statement | Meaning |
|---|---|
| `requirement checkout {` ... `}` | requirement node block |
| `element cart {` ... `}` | element node block |
| `id: REQ-1` | simple key/value field inside a node block |
| `cart - satisfies -> checkout` | typed relationship |

Relationship kinds are validated: `contains`, `copies`, `derives`, `satisfies`, `verifies`, `refines`, and `traces`. The supported subset does not include full SysML requirement semantics, nested packages, quoted rich fields, or generated IDs.

## Kanban subset

Header: `kanban`. Then:

| Statement | Meaning |
|---|---|
| `    title Delivery board` | optional title, indented by 4 spaces |
| `    todo [Todo]` | column declaration, indented by 4 spaces |
| `        REQ-1 [Write parser]` | card in the current column, indented by 8 spaces |

Columns and cards render in source order. Column/card ids match `[A-Za-z_][A-Za-z0-9_-]*`; labels are bracket text and cannot contain `]`. The supported subset does not include WIP limits, tags, assignees, priorities, swimlanes, or nested card metadata.

## Block diagram subset

Header: `block`. Then:

| Statement | Meaning |
|---|---|
| `title Layout kernel` | optional title |
| `block Solver [LayoutSolver]` | block declaration |
| `group Primitives [Primitives]` ... `end` | one-level cluster |
| `Solver -> Grid` | directed relationship |
| `Solver -> Text : measures` | labeled directed relationship |

Blocks and relationships render in source order. Block and group ids match `[A-Za-z_][A-Za-z0-9_]*`; labels are bracket text and cannot contain `]`. The supported subset does not include nested groups, ports, alternate shapes, style directives, manual coordinates, or automatic graph solving.

## Architecture subset

Header: `architecture`. Then:

| Statement | Meaning |
|---|---|
| `title Checkout platform` | optional title |
| `group Web [Web tier]` | start a group lane; subsequent nodes belong to it |
| `component App [Frontend app]` | node declaration in the current group |
| `database Orders [Orders DB]` | database node declaration |
| `App -> Api : calls` | directed relationship with optional label |

Supported node kinds are `person`, `system`, `container`, `component`, `database`, `queue`, and `external`. Group and node ids match `[A-Za-z_][A-Za-z0-9_]*`. Labels are bracket text and cannot contain `]`. The supported subset does not include full C4 semantics, icons, deployment nodes, nested arbitrary containers, or indentation-derived hierarchy.

## C4 subset

Headers: `C4Context`, `C4Container`, or `C4Component`. Then:

| Statement | Meaning |
|---|---|
| `title Shop platform` | optional title |
| `Person(buyer, "Buyer")` | person |
| `Person_Ext(auditor, "Auditor", "Reviews exports")` | external person with optional description |
| `System(shop, "Shop Platform")` | software system |
| `System_Ext(stripe, "Stripe", "Payment provider")` | external system |
| `System_Boundary(shop, "Shop Platform") {` ... `}` | one-level boundary |
| `Container(web, "Web App", "Symfony", "Checkout UI")` | container with optional technology and description |
| `ContainerDb(db, "Orders DB", "PostgreSQL")` | container database |
| `Component(api, "Orders", "PHP")` | component |
| `ComponentDb(store, "Order Store", "PostgreSQL")` | component database |
| `Rel(web, api, "calls", "HTTPS")` | directed relationship with optional technology |

Element and boundary ids match `[A-Za-z_][A-Za-z0-9_]*`. Text fields are quoted, cannot contain quotes or newlines, and are emitted canonically by `toMermaid()`. The supported subset does not include nested boundaries, deployment nodes, dynamic views, sprites, tags, links, styling macros, layout directives, or macros outside the subset above.

## Debugging

Every violation throws `Exception\ParseException`, never a silent skip. The exception message names the problem and carries the position:

```php
use Atelier\Diagram\Exception\ParseException;

try {
    Diagram::fromMermaid($source);
} catch (ParseException $e) {
    $e->getMessage();     // 'Unsupported gitGraph statement at line 4: "cherry-pick"'
    $e->getLineNumber();  // 4 (1-based, counted in the raw input)
    $e->getSourceLine();  // 'cherry-pick'
    $e->getDiagnostic()->code;             // 'parser.unsupported_syntax'
    $e->getDiagnostic()->span->startLine;  // 4
}
```

Two error classes share this shape:

- **Syntax errors**: a line the grammar does not accept.
- **Semantic errors**: a line the builder rejects (`merge` of an unknown branch, duplicate commit id, transition out of `[*]` final). The builder's `InvalidDiagramException` is wrapped in a `ParseException` carrying the offending line, with the original as `previous`. End-of-source validation reports the most specific known line when the parser tracks one, such as an empty timeline/journey section; otherwise it falls back to the header line.

`ParseException::getDiagnostic()` is the structured form for tooling. It
contains the human message without location suffix, a stable optional code, a
1-based, end-exclusive source span, and a short source excerpt when the parser
knows the offending line. `Parser\Support\ParserDiagnosticCode` is the source
of truth for known parser codes; it includes header errors, unsupported syntax,
semantic builder failures, empty labels/blocks/branches, indentation errors,
branch context errors, and unclosed block spans. The text message remains the
compatibility surface; structured diagnostics are suitable for CLI and editor output.

For human-facing tools, `Parser\Support\ParserDiagnosticFormatter` renders the
same diagnostic as a compact code frame:

```php
use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Parser\Support\ParserDiagnosticFormatter;

echo ParserDiagnosticFormatter::format($exception->getDiagnostic());

$result = (new MermaidParser())->tryParse($source);
if (null !== $result->getDiagnostic()) {
    echo ParserDiagnosticFormatter::format($result->getDiagnostic());
}
```

`Diagram::tryFromMermaid()` offers the same non-throwing shape at the facade
level. On success, `ParseResult::getModel()` is a wrapped `Diagram`; on failure,
it is `null` and `getDiagnostic()` / `getException()` expose the parse failure.

The parser bounds untrusted input before any line-level work: by default the
source is capped at **1,000,000 bytes** (`DEFAULT_MAX_SOURCE_BYTES`) and any
single raw line at **16,384 bytes** (`DEFAULT_MAX_LINE_BYTES`). These limits
are the first-line defense against oversized or pathological input and are
configurable through `ParserInputLimits`:

```php
use Atelier\Diagram\Parser\MermaidParser;
use Atelier\Diagram\Parser\Support\ParserInputLimits;

$diagram = Diagram::fromMermaid($source, new ParserInputLimits(
    maxSourceBytes: 250_000,
    maxLineBytes: 8_192,
));

$parser = new MermaidParser(limits: new ParserInputLimits(
    maxSourceBytes: 250_000,
    maxLineBytes: 8_192,
));

$diagram = $parser->parse($source);

// Per-call limits override constructor limits.
$diagram = $parser->parse($source, new ParserInputLimits(maxSourceBytes: 1_000_000));
```

Oversized payloads fail with `parser.source_too_large`; oversized raw lines
fail with `parser.line_too_long`.

## Round-trip guarantees

The parser and the markdown renderer are inverses over the subset:

- `parse(renderMermaid($model))` yields a model that renders byte-identical SVG to `$model`, except for state titles and the git legend flag, which the supported grammar cannot express and the renderer drops. Sequence and flowchart titles are serialized.
- `renderMermaid(parse($text))` is canonical: parsing the result and rendering again returns the same string. Equivalent spellings normalize (`A : d` → `state "d" as A`, `switch` → `checkout`, attribute order `id` then `tag`); the user's original formatting is not preserved.
- Auto-generated commit ids are deterministic and omitted on rendering, so they survive any number of round-trips.
- Auto-declared participants, flow nodes, and classes are serialized explicitly, so the canonical form is stable.
- Sequence `loop` / `alt` / `opt` / `par` blocks, activations, and nested flowchart subgraphs are serialized canonically.
