---
order: 20
---
# Every Diagram Type

Fifteen types, each with a fluent PHP builder, a layout engine, and an SVG renderer. All of them except Venn also parse a Mermaid subset and render canonical Mermaid back.

Every figure below is generated from the type's own documented example, so what you see is what the code in that page produces.

<div class="figure-grid">
<figure><a href="flowchart.md"><img src="../images/flowchart.svg" alt="A deployment workflow flowing from top to bottom"></a><figcaption><a href="flowchart.md">Flowchart</a></figcaption></figure>
<figure><a href="state-diagram.md"><img src="../images/state-diagram.svg" alt="A state machine for an order"></a><figcaption><a href="state-diagram.md">State</a></figcaption></figure>
<figure><a href="sequence-diagram.md"><img src="../images/sequence-diagram.svg" alt="A sequence diagram with participants and messages"></a><figcaption><a href="sequence-diagram.md">Sequence</a></figcaption></figure>
<figure><a href="class-diagram.md"><img src="../images/class-diagram.svg" alt="A class diagram with members and relations"></a><figcaption><a href="class-diagram.md">Class</a></figcaption></figure>
<figure><a href="er-diagram.md"><img src="../images/er-diagram.svg" alt="An entity relationship diagram"></a><figcaption><a href="er-diagram.md">ER</a></figcaption></figure>
<figure><a href="git-graph.md"><img src="../images/git-graph.svg" alt="A branching git history"></a><figcaption><a href="git-graph.md">Git graph</a></figcaption></figure>
<figure><a href="venn-diagram.md"><img src="../images/venn-diagram.svg" alt="Three overlapping sets"></a><figcaption><a href="venn-diagram.md">Venn</a></figcaption></figure>
<figure><a href="timeline-diagram.md"><img src="../images/timeline-diagram.svg" alt="A timeline with sections and dated events"></a><figcaption><a href="timeline-diagram.md">Timeline</a></figcaption></figure>
<figure><a href="journey-diagram.md"><img src="../images/journey-diagram.svg" alt="A user journey with scored tasks"></a><figcaption><a href="journey-diagram.md">Journey</a></figcaption></figure>
<figure><a href="mindmap-diagram.md"><img src="../images/mindmap-diagram.svg" alt="A mindmap branching from a central root"></a><figcaption><a href="mindmap-diagram.md">Mindmap</a></figcaption></figure>
<figure><a href="requirement-diagram.md"><img src="../images/requirement-diagram.svg" alt="A requirement diagram with relationships"></a><figcaption><a href="requirement-diagram.md">Requirement</a></figcaption></figure>
<figure><a href="kanban-diagram.md"><img src="../images/kanban-diagram.svg" alt="A kanban board with columns and cards"></a><figcaption><a href="kanban-diagram.md">Kanban</a></figcaption></figure>
<figure><a href="block-diagram.md"><img src="../images/block-diagram.svg" alt="A block diagram with grouped blocks"></a><figcaption><a href="block-diagram.md">Block</a></figcaption></figure>
<figure><a href="architecture-diagram.md"><img src="../images/architecture-diagram.svg" alt="An architecture diagram with grouped services"></a><figcaption><a href="architecture-diagram.md">Architecture</a></figcaption></figure>
<figure><a href="c4-diagram.md"><img src="../images/c4-diagram.svg" alt="A C4 diagram with systems and people"></a><figcaption><a href="c4-diagram.md">C4</a></figcaption></figure>
</div>

## Which One Parses Mermaid

Fourteen of the fifteen accept a Mermaid subset. Venn has none, because Mermaid itself has no Venn diagram: it is built through the PHP builder only.

Each type page names its header keyword and links the exact grammar it accepts. The full table lives in [Mermaid support](../mermaid.md).
