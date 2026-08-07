<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Sequence\Message;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\Participant;
use Atelier\Diagram\Sequence\SequenceActivation;
use Atelier\Diagram\Sequence\SequenceBlock;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagram;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequenceDiagram::class)]
#[CoversClass(SequenceDiagramBuilder::class)]
#[CoversClass(SequenceBlock::class)]
final class SequenceDiagramBuilderTest extends TestCase
{
    public function testBuildsParticipantsAndMessagesInDeclarationOrder(): void
    {
        $diagram = (new SequenceDiagramBuilder())
            ->participant('User', 'Customer')
            ->participant('Api', 'API')
            ->message('User', 'Api', 'Checkout')
            ->message('Api', 'User', 'Done', MessageArrow::Dashed)
            ->block(SequenceBlockKind::Alt, 'retry', 0, 1, [
                new SequenceBlockBranch('primary', 0, 0),
                new SequenceBlockBranch('fallback', 1, 1),
            ])
            ->build();

        $this->assertSame(['User', 'Api'], array_map(static fn ($participant): string => $participant->id, $diagram->participants));
        $this->assertSame('Customer', $diagram->participants[0]->label);
        $this->assertSame('Checkout', $diagram->messages[0]->label);
        $this->assertSame(MessageArrow::Dashed, $diagram->messages[1]->arrow);
        $this->assertSame(SequenceBlockKind::Alt, $diagram->blocks[0]->kind);
        $this->assertSame('retry', $diagram->blocks[0]->label);
        $this->assertSame('fallback', $diagram->blocks[0]->branches[1]->label);
    }

    public function testBuildsActivationRanges(): void
    {
        $diagram = (new SequenceDiagramBuilder())
            ->message('Client', 'Server', 'Call')
            ->activate('Server')
            ->message('Server', 'Client', 'Return')
            ->deactivate('Server')
            ->build();

        $this->assertSame('Server', $diagram->activations[0]->participant);
        $this->assertSame(0, $diagram->activations[0]->firstMessageIndex);
        $this->assertSame(1, $diagram->activations[0]->lastMessageIndex);
    }

    public function testTitleMessageCountAndImplicitActivationOnBuild(): void
    {
        $builder = (new SequenceDiagramBuilder())
            ->title('Checkout flow')
            ->message('Client', 'Server', 'Call')
            ->activate('Server');

        $this->assertSame(1, $builder->messageCount());

        $diagram = $builder->build();

        $this->assertNotNull($diagram->title);
        $this->assertSame('Checkout flow', $diagram->title->text);
        $this->assertSame('Server', $diagram->activations[0]->participant);
        $this->assertSame(0, $diagram->activations[0]->firstMessageIndex);
        $this->assertSame(0, $diagram->activations[0]->lastMessageIndex);
    }

    public function testMessagesAutoDeclareUnknownParticipants(): void
    {
        $diagram = (new SequenceDiagramBuilder())
            ->message('Client', 'Server', 'Ping')
            ->build();

        $this->assertSame(['Client', 'Server'], array_map(static fn ($participant): string => $participant->id, $diagram->participants));
    }

    public function testRejectsDuplicateParticipants(): void
    {
        $builder = (new SequenceDiagramBuilder())->participant('A');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already declared');

        $builder->participant('A');
    }

    public function testRejectsEmptyMessageLabels(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('message label');

        (new SequenceDiagramBuilder())->message('A', 'B', '  ');
    }

    public function testRejectsActivatingAlreadyActiveParticipant(): void
    {
        $builder = (new SequenceDiagramBuilder())
            ->message('A', 'B', 'Call')
            ->activate('B');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already active');

        $builder->activate('B');
    }

    public function testActivationMustFollowAMessage(): void
    {
        $builder = (new SequenceDiagramBuilder())->participant('A');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must follow a message');

        $builder->activate('A');
    }

    public function testRejectsDeactivatingInactiveParticipant(): void
    {
        $builder = (new SequenceDiagramBuilder())
            ->message('A', 'B', 'Call');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not active');

        $builder->deactivate('B');
    }

    public function testBuildRequiresAtLeastOneParticipant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one participant');

        (new SequenceDiagramBuilder())->build();
    }

    public function testRejectsEmptyBlockLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('block label');

        new SequenceBlock(SequenceBlockKind::Loop, '  ', 0, 0);
    }

    public function testRejectsInvalidBlockRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('block message range');

        new SequenceBlock(SequenceBlockKind::Loop, 'retry', 2, 1);
    }

    public function testRejectsBranchOutsideBlockRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inside its block range');

        new SequenceBlock(SequenceBlockKind::Alt, 'pick', 0, 1, [
            new SequenceBlockBranch('overflow', 0, 2),
        ]);
    }

    public function testRejectsEmptyParticipantList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one participant');

        /* @phpstan-ignore argument.type */
        new SequenceDiagram([], []);
    }

    public function testRejectsDuplicateParticipantIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate sequence participant id');

        new SequenceDiagram([new Participant('A', 'A'), new Participant('A', 'Other')], []);
    }

    public function testRejectsMessageFromUnknownParticipant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown participant "X"');

        new SequenceDiagram([new Participant('A', 'A')], [new Message('X', 'A', 'Call')]);
    }

    public function testRejectsMessageToUnknownParticipant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown participant "Y"');

        new SequenceDiagram([new Participant('A', 'A')], [new Message('A', 'Y', 'Call')]);
    }

    public function testRejectsBlockRangeOutsideDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('message range outside the diagram');

        new SequenceDiagram(
            [new Participant('A', 'A')],
            [new Message('A', 'A', 'Call')],
            null,
            [new SequenceBlock(SequenceBlockKind::Loop, 'retry', 0, 1)],
        );
    }

    public function testRejectsActivationForUnknownParticipant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('activation references unknown participant "Z"');

        new SequenceDiagram(
            [new Participant('A', 'A')],
            [new Message('A', 'A', 'Call')],
            null,
            [],
            [new SequenceActivation('Z', 0, 0)],
        );
    }

    public function testRejectsActivationRangeOutsideDiagram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('activation references a message range outside the diagram');

        new SequenceDiagram(
            [new Participant('A', 'A')],
            [new Message('A', 'A', 'Call')],
            null,
            [],
            [new SequenceActivation('A', 0, 1)],
        );
    }
}
