<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Title;

/**
 * Fluent builder for sequence diagrams.
 */
final class SequenceDiagramBuilder
{
    /**
     * @var array<string, Participant>
     */
    private array $participants = [];

    /**
     * @var list<Message>
     */
    private array $messages = [];

    /**
     * @var list<SequenceBlock>
     */
    private array $blocks = [];

    /**
     * @var list<SequenceActivation>
     */
    private array $activations = [];

    /**
     * @var array<string, int>
     */
    private array $activeParticipants = [];

    private ?Title $title = null;

    public function title(string $text): self
    {
        $this->title = new Title($text);

        return $this;
    }

    public function participant(string $id, ?string $label = null): self
    {
        $label ??= $id;
        if (isset($this->participants[$id])) {
            throw new InvalidArgumentException(\sprintf('Sequence participant "%s" is already declared.', $id));
        }

        $this->participants[$id] = new Participant($id, $label);

        return $this;
    }

    public function message(string $from, string $to, string $label, MessageArrow $arrow = MessageArrow::Solid): self
    {
        $this->ensureParticipant($from);
        $this->ensureParticipant($to);
        $this->messages[] = new Message($from, $to, $label, $arrow);

        return $this;
    }

    /**
     * @param list<SequenceBlockBranch> $branches
     */
    public function block(SequenceBlockKind $kind, string $label, int $firstMessageIndex, int $lastMessageIndex, array $branches = []): self
    {
        $this->blocks[] = new SequenceBlock($kind, $label, $firstMessageIndex, $lastMessageIndex, $branches);

        return $this;
    }

    public function activate(string $participant): self
    {
        $this->ensureParticipant($participant);
        if (isset($this->activeParticipants[$participant])) {
            throw new InvalidArgumentException(\sprintf('Sequence participant "%s" is already active.', $participant));
        }
        if ([] === $this->messages) {
            throw new InvalidArgumentException('Sequence activation must follow a message.');
        }

        $this->activeParticipants[$participant] = \count($this->messages) - 1;

        return $this;
    }

    public function deactivate(string $participant): self
    {
        $this->ensureParticipant($participant);
        if (!isset($this->activeParticipants[$participant])) {
            throw new InvalidArgumentException(\sprintf('Sequence participant "%s" is not active.', $participant));
        }

        $this->activations[] = new SequenceActivation($participant, $this->activeParticipants[$participant], max(0, \count($this->messages) - 1));
        unset($this->activeParticipants[$participant]);

        return $this;
    }

    public function messageCount(): int
    {
        return \count($this->messages);
    }

    public function build(): SequenceDiagram
    {
        if ([] === $this->participants) {
            throw new InvalidArgumentException('Sequence diagram must contain at least one participant.');
        }

        foreach ($this->activeParticipants as $participant => $firstMessageIndex) {
            $this->activations[] = new SequenceActivation($participant, $firstMessageIndex, max(0, \count($this->messages) - 1));
        }
        $this->activeParticipants = [];

        return new SequenceDiagram(array_values($this->participants), $this->messages, $this->title, $this->blocks, $this->activations);
    }

    private function ensureParticipant(string $id): void
    {
        if (!isset($this->participants[$id])) {
            $this->participants[$id] = new Participant($id, $id);
        }
    }
}
