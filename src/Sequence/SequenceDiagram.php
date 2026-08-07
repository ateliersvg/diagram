<?php

declare(strict_types=1);

namespace Atelier\Diagram\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Title;

/**
 * Mermaid-like sequence diagram model.
 *
 * Participants are ordered lanes. Messages refer to participant ids and
 * are rendered in declaration order.
 */
final readonly class SequenceDiagram implements DiagramModel
{
    /**
     * @param non-empty-list<Participant> $participants
     * @param list<Message>               $messages
     * @param list<SequenceBlock>         $blocks
     * @param list<SequenceActivation>    $activations
     */
    public function __construct(
        public array $participants,
        public array $messages,
        public ?Title $title = null,
        public array $blocks = [],
        public array $activations = [],
    ) {
        if ([] === $participants) {
            throw new InvalidArgumentException('Sequence diagram must contain at least one participant.');
        }

        $known = [];
        foreach ($participants as $participant) {
            if (isset($known[$participant->id])) {
                throw new InvalidArgumentException(\sprintf('Duplicate sequence participant id "%s".', $participant->id));
            }
            $known[$participant->id] = true;
        }

        foreach ($messages as $message) {
            if (!isset($known[$message->from])) {
                throw new InvalidArgumentException(\sprintf('Sequence message references unknown participant "%s".', $message->from));
            }
            if (!isset($known[$message->to])) {
                throw new InvalidArgumentException(\sprintf('Sequence message references unknown participant "%s".', $message->to));
            }
        }

        foreach ($blocks as $block) {
            if (!isset($messages[$block->firstMessageIndex]) || !isset($messages[$block->lastMessageIndex])) {
                throw new InvalidArgumentException('Sequence block references a message range outside the diagram.');
            }
        }

        foreach ($activations as $activation) {
            if (!isset($known[$activation->participant])) {
                throw new InvalidArgumentException(\sprintf('Sequence activation references unknown participant "%s".', $activation->participant));
            }
            if (!isset($messages[$activation->firstMessageIndex]) || !isset($messages[$activation->lastMessageIndex])) {
                throw new InvalidArgumentException('Sequence activation references a message range outside the diagram.');
            }
        }
    }
}
