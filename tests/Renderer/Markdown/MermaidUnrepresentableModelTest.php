<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Markdown;

use Atelier\Diagram\Architecture\ArchitectureDiagram;
use Atelier\Diagram\Architecture\ArchitectureGroup;
use Atelier\Diagram\Architecture\ArchitectureNode;
use Atelier\Diagram\Architecture\ArchitectureNodeKind;
use Atelier\Diagram\Block\BlockDiagram;
use Atelier\Diagram\Block\BlockGroup;
use Atelier\Diagram\Block\BlockNode;
use Atelier\Diagram\ClassDiagram\ClassBox;
use Atelier\Diagram\ClassDiagram\ClassDiagram;
use Atelier\Diagram\ClassDiagram\ClassMember;
use Atelier\Diagram\Er\ErAttribute;
use Atelier\Diagram\Er\ErDiagram;
use Atelier\Diagram\Er\ErEntity;
use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Flow\Flowchart;
use Atelier\Diagram\Flow\FlowNode;
use Atelier\Diagram\Git\Branch;
use Atelier\Diagram\Git\Commit;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Journey\JourneyActor;
use Atelier\Diagram\Journey\JourneyDiagram;
use Atelier\Diagram\Journey\JourneySection;
use Atelier\Diagram\Journey\JourneyTask;
use Atelier\Diagram\Kanban\KanbanColumn;
use Atelier\Diagram\Kanban\KanbanDiagram;
use Atelier\Diagram\Mindmap\MindmapDiagram;
use Atelier\Diagram\Mindmap\MindmapNode;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Renderer\Markdown\ArchitectureDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\BlockDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\ClassDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\ErDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\FlowchartSerializer;
use Atelier\Diagram\Renderer\Markdown\GitGraphSerializer;
use Atelier\Diagram\Renderer\Markdown\JourneyDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\KanbanDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\MindmapDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\RequirementDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\SequenceDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\StateDiagramSerializer;
use Atelier\Diagram\Renderer\Markdown\TimelineDiagramSerializer;
use Atelier\Diagram\Requirement\RequirementDiagram;
use Atelier\Diagram\Requirement\RequirementNode;
use Atelier\Diagram\Requirement\RequirementNodeKind;
use Atelier\Diagram\Sequence\Message;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\Participant;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\State\State;
use Atelier\Diagram\State\StateDiagram;
use Atelier\Diagram\State\Transition;
use Atelier\Diagram\Timeline\TimelineDiagram;
use Atelier\Diagram\Timeline\TimelineEvent;
use Atelier\Diagram\Timeline\TimelineSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateDiagramSerializer::class)]
#[CoversClass(GitGraphSerializer::class)]
#[CoversClass(SequenceDiagramSerializer::class)]
#[CoversClass(FlowchartSerializer::class)]
#[CoversClass(ClassDiagramSerializer::class)]
#[CoversClass(ErDiagramSerializer::class)]
#[CoversClass(TimelineDiagramSerializer::class)]
#[CoversClass(JourneyDiagramSerializer::class)]
#[CoversClass(MindmapDiagramSerializer::class)]
#[CoversClass(RequirementDiagramSerializer::class)]
#[CoversClass(KanbanDiagramSerializer::class)]
#[CoversClass(BlockDiagramSerializer::class)]
#[CoversClass(ArchitectureDiagramSerializer::class)]
final class MermaidUnrepresentableModelTest extends TestCase
{
    /**
     * @param \Closure(): string $render
     */
    #[DataProvider('unrepresentableModels')]
    public function testSerializersRejectModelsOutsideSupportedMermaidSubset(\Closure $render, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $render();
    }

    /**
     * @return iterable<string, array{\Closure(): string, string}>
     */
    public static function unrepresentableModels(): iterable
    {
        yield 'state invalid id' => [
            static fn (): string => (new StateDiagramSerializer())->serialize(new StateDiagram(
                Direction::TopToBottom,
                [new State('Bad Id')],
                [],
            )),
            'state id "Bad Id" is not a valid Mermaid identifier',
        ];

        yield 'state quote in label' => [
            static fn (): string => (new StateDiagramSerializer())->serialize(new StateDiagram(
                Direction::TopToBottom,
                [new State('Ready', 'Needs "quote"')],
                [],
            )),
            'label of state "Ready" must not contain quotes or newlines',
        ];

        yield 'git initial branch is not main' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('trunk')],
                commits: [],
            )),
            'the first branch must be the initial "main" branch',
        ];

        yield 'git tagged merge cannot be expressed' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main'), new Branch('feature', 'c1')],
                commits: [
                    new Commit('c1', 'main'),
                    new Commit('c2', 'feature', parents: ['c1']),
                    new Commit('commit-3', 'main', 'merge-tag', ['c1', 'c2']),
                ],
            )),
            'merge commit "commit-3" must have exactly two parents and no tag',
        ];

        yield 'sequence invalid participant id' => [
            static fn (): string => (new SequenceDiagramSerializer())->serialize(new SequenceDiagram(
                participants: [new Participant('Bad Id', 'Bad Id'), new Participant('Api', 'Api')],
                messages: [new Message('Bad Id', 'Api', 'Ping', MessageArrow::Solid)],
            )),
            'participant id "Bad Id" is not a supported identifier',
        ];

        yield 'flow label contains control marker' => [
            static fn (): string => (new FlowchartSerializer())->serialize(new Flowchart(
                Direction::TopToBottom,
                [new FlowNode('A', 'Bad | Label')],
                [],
            )),
            'label of node "A" contains unsupported control characters',
        ];

        yield 'class member contains newline' => [
            static fn (): string => (new ClassDiagramSerializer())->serialize(new ClassDiagram([
                new ClassBox('User', [new ClassMember("+email string\n+name string")]),
            ])),
            'member of class "User" contains unsupported control characters',
        ];

        yield 'er attribute type contains whitespace' => [
            static fn (): string => (new ErDiagramSerializer())->serialize(new ErDiagram([
                new ErEntity('CUSTOMER', [new ErAttribute('var char', 'email')]),
            ])),
            'attribute type "var char" is not a supported token',
        ];

        yield 'timeline event date contains newline' => [
            static fn (): string => (new TimelineDiagramSerializer())->serialize(new TimelineDiagram([
                new TimelineSection('Build', [new TimelineEvent('Launch', "2026-06\n2026-07")]),
            ])),
            'timeline event date contains unsupported control characters',
        ];

        yield 'journey actor contains comma' => [
            static fn (): string => (new JourneyDiagramSerializer())->serialize(new JourneyDiagram([
                new JourneySection('Checkout', [new JourneyTask('Pay', 4, [new JourneyActor('Customer, PSP')])]),
            ])),
            'actor name "Customer, PSP" must not contain commas',
        ];

        yield 'mindmap label contains shape syntax' => [
            static fn (): string => (new MindmapDiagramSerializer())->serialize(new MindmapDiagram(
                new MindmapNode('root', 'Atelier', [new MindmapNode('bad', 'Child((Bad))')]),
            )),
            'node label contains unsupported shape syntax',
        ];

        yield 'requirement field name is not supported' => [
            static fn (): string => (new RequirementDiagramSerializer())->serialize(new RequirementDiagram([
                new RequirementNode('checkout', RequirementNodeKind::Requirement, ['bad-name' => 'value']),
            ])),
            'field name "bad-name" is not a supported identifier',
        ];

        yield 'kanban label contains bracket' => [
            static fn (): string => (new KanbanDiagramSerializer())->serialize(new KanbanDiagram([
                new KanbanColumn('todo', 'Todo]', []),
            ])),
            'column label contains unsupported bracket syntax',
        ];

        yield 'block label contains bracket' => [
            static fn (): string => (new BlockDiagramSerializer())->serialize(new BlockDiagram(
                [new BlockNode('Solver', 'Layout]Solver')],
            )),
            'block label contains unsupported control characters',
        ];

        yield 'block group id is not supported' => [
            static fn (): string => (new BlockDiagramSerializer())->serialize(new BlockDiagram(
                [new BlockNode('Solver', 'Solver', 'Core Group')],
                [new BlockGroup('Core Group', 'Core', ['Solver'])],
            )),
            'block group id "Core Group" is not a supported identifier',
        ];

        yield 'architecture label contains bracket' => [
            static fn (): string => (new ArchitectureDiagramSerializer())->serialize(new ArchitectureDiagram(
                [new ArchitectureGroup('Web', new Label('Web tier'))],
                [new ArchitectureNode('App', ArchitectureNodeKind::Component, new Label('Frontend [app]'), 'Web')],
            )),
            'label of node "App" contains unsupported control characters',
        ];

        yield 'state transition endpoint is not a declared state' => [
            static fn (): string => (new StateDiagramSerializer())->serialize(new StateDiagram(
                Direction::TopToBottom,
                [new State('A')],
                [new Transition('A', 'Ghost')],
            )),
            'transition endpoint "Ghost" is not a declared state',
        ];

        yield 'state transition label is not a single trimmed line' => [
            static fn (): string => (new StateDiagramSerializer())->serialize(new StateDiagram(
                Direction::TopToBottom,
                [new State('A'), new State('B')],
                [new Transition('A', 'B', new Label(' submit '))],
            )),
            'must be a single trimmed non-empty line',
        ];

        yield 'git branch name contains whitespace' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main'), new Branch('')],
                commits: [],
            )),
            'branch name "" must be non-empty and contain no whitespace',
        ];

        yield 'git pending branch forks from a missing commit' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main'), new Branch('feature', 'ghost')],
                commits: [],
            )),
            'branch "feature" is created from commit "ghost", which does not exist',
        ];

        yield 'git commit sits on an uncreated branch' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main')],
                commits: [new Commit('c1', 'feature')],
            )),
            'commit "c1" sits on branch "feature", which is not created at that point of the history',
        ];

        yield 'git commit id is used twice' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main')],
                commits: [new Commit('dup', 'main'), new Commit('dup', 'main')],
            )),
            'commit id "dup" is used by two commits',
        ];

        yield 'git commit parents do not match the branch tip' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main')],
                commits: [new Commit('c1', 'main'), new Commit('c2', 'main', parents: ['wrong'])],
            )),
            'the parents of commit "c2" do not match the tip of branch "main"',
        ];

        yield 'git merge first parent is not the branch tip' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main'), new Branch('feature', 'c1')],
                commits: [
                    new Commit('c1', 'main'),
                    new Commit('c2', 'feature', parents: ['c1']),
                    new Commit('merge', 'main', parents: ['wrong', 'c2']),
                ],
            )),
            'the first parent of merge commit "merge" is not the tip of branch "main"',
        ];

        yield 'git merge id cannot be expressed' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main'), new Branch('feature', 'c1')],
                commits: [
                    new Commit('c1', 'main'),
                    new Commit('c2', 'feature', parents: ['c1']),
                    new Commit('not-auto', 'main', parents: ['c1', 'c2']),
                ],
            )),
            'the id of merge commit "not-auto" cannot be expressed',
        ];

        // The merge's second parent ("c2") is no longer any branch's tip
        // (feature has advanced to "c3"), so no fork/merge point is found.
        // The merge id must equal the builder auto-id at this point so the
        // serializer reaches findBranchWithTip() rather than failing earlier.
        yield 'git merge point is not the tip of any branch' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main'), new Branch('feature', 'c1')],
                commits: [
                    new Commit('c1', 'main'),
                    new Commit('c2', 'feature', parents: ['c1']),
                    new Commit('c3', 'feature', parents: ['c2']),
                    new Commit('b1ea5e0', 'main', parents: ['c1', 'c2']),
                ],
            )),
            'no branch has commit "c2" as tip when it is needed as a fork or merge point',
        ];

        yield 'git commit id contains a quote' => [
            static fn (): string => (new GitGraphSerializer())->serialize(new GitGraph(
                branches: [new Branch('main')],
                commits: [new Commit('bad"id', 'main')],
            )),
            'must not contain quotes or newlines',
        ];
    }
}
