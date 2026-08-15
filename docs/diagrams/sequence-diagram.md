---
order: 30
title: Sequence
---
# Sequence Diagrams

A sequence diagram is a set of participants and the ordered messages exchanged between them over time, with optional activation bars and grouping blocks.

<figure class="diagram-intro">
<img src="../images/sequence-diagram.svg" alt="A sequence diagram with participants, messages, and activation bars">
<figcaption>Participants exchanging ordered messages over time.</figcaption>
</figure>

- [Overview](#overview)
- [Format](#format)
- [Builder API](#builder-api)
- [Options](#options)
- [Themes](#themes)
- [Parse](#parse)
- [Debug](#debug)

## Overview

The model lives in `Atelier\Diagram\Sequence\`: `SequenceDiagram`, `Participant` (`id`, `label` defaulting to the id), `Message` (`from`, `to`, `label`, `arrow`), `SequenceBlock` (a `SequenceBlockKind` grouping a range of messages, with optional `SequenceBlockBranch` branches), and `SequenceActivation` (an activation bar spanning a message range). Arrows are the `MessageArrow` enum, block kinds the `SequenceBlockKind` enum. All model classes are immutable.

Reach for it to show a conversation ordered in time: a client and a server exchanging requests and replies, a checkout flow across services, a handshake. Use a [flowchart](flowchart.md) instead when the order is branching logic rather than messages, or a [state diagram](state-diagram.md) when you track one subject through its states.

## Format

Sequence diagrams round-trip through a `sequenceDiagram` subset:

```mermaid
sequenceDiagram
    title Checkout
    participant User as Customer
    participant Api as API
    loop retry
    User->>Api: Pay
    Api-->>User: Receipt
    end
    alt fallback
    activate Api
    Api->>Api: Validate
    deactivate Api
    else manual
    User->>Api: Manual review
    end
    opt notify
    Api-->>User: Email
    end
    par audit
    Api->>Api: Store audit
    and metrics
    Api->>Api: Store metrics
    end
```

`->>` is a solid message, `-->>` a dashed reply. `loop` / `alt` / `opt` / `par` blocks close with `end`; `else` and `and` separate branches inside `alt` and `par`. `activate` / `deactivate` draw an activation bar. Messages auto-declare unknown participants. Nested blocks, actors, notes, autonumber, and destruction markers are rejected, never skipped. Full grammar: [Mermaid support](../mermaid.md#sequence-diagram-subset).

## Builder API

`SequenceDiagramBuilder` (or `Diagram::sequence()`, which returns it) is the one way to build the model:

```php
use Atelier\Diagram\Diagram;
use Atelier\Diagram\Sequence\MessageArrow;

$diagram = Diagram::sequence()
    ->title('Checkout')
    ->participant('User', 'Customer')           // id + display label
    ->participant('Api', 'API')
    ->message('User', 'Api', 'Pay')             // solid arrow
    ->message('Api', 'User', 'Receipt', MessageArrow::Dashed)
    ->message('Api', 'Api', 'Validate')         // self-message
    ->build();

Diagram::of($diagram)->saveSvg('checkout.svg');
```

`title()`
: Sets a title serialized to Mermaid.

  | Argument | Type | Description |
  |---|---|---|
  | `$text` | `string` | Non-empty title. |

`participant()`
: Declares a participant. Re-declaring an id throws.

  | Argument | Type | Description |
  |---|---|---|
  | `$id` | `string` | Unique participant identifier. |
  | `$label` | `?string` | Display label; defaults to `$id`. |

`message()`
: Adds a message and auto-declares unknown endpoints.

  | Argument | Type | Description |
  |---|---|---|
  | `$from` | `string` | Source participant identifier. |
  | `$to` | `string` | Target participant identifier. |
  | `$label` | `string` | Message label. |
  | `$arrow` | `MessageArrow` | `Solid` by default, or `Dashed`. |

`block()`
: Groups a message range as `Loop`, `Alt`, `Opt`, or `Par`, with optional branches.

  | Argument | Type | Description |
  |---|---|---|
  | `$kind` | `SequenceBlockKind` | Block kind. |
  | `$label` | `string` | Block label. |
  | `$firstMessageIndex` | `int` | First included message index. |
  | `$lastMessageIndex` | `int` | Last included message index. |
  | `$branches` | `list<SequenceBlockBranch>` | Optional branch definitions. |

`activate()` / `deactivate()`
: Opens or closes an activation bar. Invalid activation state throws; bars left open at `build()` close at the last message.

  | Argument | Type | Description |
  |---|---|---|
  | `$participant` | `string` | Participant identifier. |

`messageCount()`
: Returns the current message count for computing block index ranges.

`build()`
: Assembles the immutable `SequenceDiagram`. A diagram without participants throws.

## Options

| Setting | Default | Effect |
|---|---|---|
| `title(string)` | none | heading included in Mermaid serialization |
| `block(kind, label, first, last, branches)` | none | frames a contiguous message range; branches split it |
| `activate()` / `deactivate()` | none | activation bars over a lifeline |

- **Determinism**: layout is source-order based and deterministic; the same model always yields the same SVG.

`Layout\Sequence\SequenceLayoutEngine` solves participant tracks with `atelier/layout`, then draws headers, dotted lifelines, block spans, message rows with labels and arrowheads, and self-message loops into the shared `Scene` IR. Dashed messages use a dashed line; self-messages draw a small loop back to the same lifeline.

## Themes

Sequence diagrams use `nodeFillColor` and `nodeStrokeColor` for participant headers and activation bars, `nodeStrokeColor` for message lines and arrowheads, `mutedTextColor` for lifelines and block frames, `textColor` for message labels, and `backgroundColor` behind label halos. Block frames are unfilled outlines stroked with `mutedTextColor`, so they sit cleanly on any preset.

All presets apply (`Theme::default()`, `dark()`, `blueprint()`, `mono()`, `neutral()`); no `Scene\BackgroundPattern` is drawn for this type. See [Theming](../theming.md).

<img src="../images/sequence-diagram-theme.svg" alt="The same sequence diagram in the mono preset">

`Theme::mono()`: a single ink, no accent cycle. Useful when the output is going to be printed or embedded in a document that owns its own colours.

## Parse

Header keyword: `sequenceDiagram`.

```php
$diagram = Diagram::fromMermaid($source);        // throws ParseException
$result  = Diagram::tryFromMermaid($source);      // non-throwing ParseResult
```

`toMermaid()` / `toMarkdown()` serialize titles, blocks, and activations in canonical form. Full grammar and round-trip rules: [Mermaid support](../mermaid.md#sequence-diagram-subset).

## Debug

On a rejected source, inspect `ParseResult::getDiagnostic()` (a `ParserDiagnostic` with a message, a stable `code`, and a source span) or catch `ParseException` (`getLineNumber()`, `getSourceLine()`). Render a code frame with `ParserDiagnosticFormatter`:

```php
$result = Diagram::tryFromMermaid($source);
if ($result->isFailure()) {
    echo \Atelier\Diagram\Parser\Support\ParserDiagnosticFormatter::format($result->getDiagnostic());
}
```

See [parser diagnostics](../mermaid.md#debugging).
