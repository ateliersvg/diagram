<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Sequence\Message;
use Atelier\Diagram\Sequence\Participant;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagram;

/**
 * Serializes SequenceDiagram to canonical Mermaid sequenceDiagram source.
 *
 * The canonical form emits all participants first, then messages in model
 * order. Auto-created participants are serialized explicitly so parse /
 * render / parse stays stable.
 *
 * @internal used by MarkdownRenderer
 */
final class SequenceDiagramSerializer
{
    public function serialize(SequenceDiagram $diagram): string
    {
        $lines = ['sequenceDiagram'];

        if (null !== $diagram->title) {
            $this->assertSerializableText($diagram->title->text, 'title');
            $lines[] = '    title '.$diagram->title->text;
        }

        foreach ($diagram->participants as $participant) {
            $lines[] = '    '.$this->participantLine($participant);
        }

        $starts = [];
        $ends = [];
        $branches = [];
        foreach ($diagram->blocks as $block) {
            $starts[$block->firstMessageIndex][] = $block;
            $ends[$block->lastMessageIndex][] = $block;
            foreach ($block->branches as $branch) {
                if ($branch->firstMessageIndex !== $block->firstMessageIndex) {
                    $branches[$branch->firstMessageIndex][] = [$block, $branch];
                }
            }
        }

        $activations = [];
        $deactivations = [];
        foreach ($diagram->activations as $activation) {
            $activations[$activation->firstMessageIndex][] = $activation->participant;
            $deactivations[$activation->lastMessageIndex][] = $activation->participant;
        }

        foreach ($diagram->messages as $index => $message) {
            foreach ($starts[$index] ?? [] as $block) {
                $this->assertSerializableText($block->label, \sprintf('label of %s block', $block->kind->value));
                $lines[] = \sprintf('    %s %s', $block->kind->value, $block->label);
            }
            foreach ($branches[$index] ?? [] as [$block, $branch]) {
                $this->assertSerializableText($branch->label, \sprintf('label of %s branch', $block->kind->value));
                $lines[] = \sprintf('    %s %s', SequenceBlockKind::Par === $block->kind ? 'and' : 'else', $branch->label);
            }
            $lines[] = '    '.$this->messageLine($message);
            foreach ($activations[$index] ?? [] as $participant) {
                $this->assertSerializableId($participant, 'activation participant id');
                $lines[] = '    activate '.$participant;
            }
            foreach ($deactivations[$index] ?? [] as $participant) {
                $this->assertSerializableId($participant, 'deactivation participant id');
                $lines[] = '    deactivate '.$participant;
            }
            foreach ($ends[$index] ?? [] as $block) {
                $lines[] = '    end';
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function participantLine(Participant $participant): string
    {
        $this->assertSerializableId($participant->id, 'participant id');
        $this->assertSerializableText($participant->label, \sprintf('label of participant "%s"', $participant->id));

        if ($participant->label === $participant->id) {
            return 'participant '.$participant->id;
        }

        return \sprintf('participant %s as %s', $participant->id, $participant->label);
    }

    private function messageLine(Message $message): string
    {
        $this->assertSerializableId($message->from, 'message source id');
        $this->assertSerializableId($message->to, 'message target id');
        $this->assertSerializableText($message->label, \sprintf('label of message "%s" to "%s"', $message->from, $message->to));

        return \sprintf('%s%s%s: %s', $message->from, $message->arrow->value, $message->to, $message->label);
    }

    private function assertSerializableId(string $id, string $what): void
    {
        MermaidSerializable::strictId($id, 'sequence diagram', $what);
    }

    private function assertSerializableText(string $text, string $what): void
    {
        MermaidSerializable::text($text, 'sequence diagram', $what, '/[\r\n]/', 'must not contain newlines');
    }
}
