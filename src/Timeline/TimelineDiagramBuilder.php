<?php

declare(strict_types=1);

namespace Atelier\Diagram\Timeline;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Title;

final class TimelineDiagramBuilder
{
    /**
     * @var list<array{title: string, events: list<TimelineEvent>}>
     */
    private array $sections = [];

    private ?Title $title = null;

    public function title(string $text): self
    {
        $this->title = new Title($text);

        return $this;
    }

    public function section(string $title): self
    {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Timeline section title must be non-empty.');
        }

        $this->sections[] = ['title' => $title, 'events' => []];

        return $this;
    }

    public function event(string $label, string $date): self
    {
        if ([] === $this->sections) {
            throw new InvalidArgumentException('Timeline event must belong to a section.');
        }

        $index = \count($this->sections) - 1;
        $this->sections[$index]['events'][] = new TimelineEvent($label, $date);

        return $this;
    }

    public function build(): TimelineDiagram
    {
        if ([] === $this->sections) {
            throw new InvalidArgumentException('Timeline diagram must contain at least one section.');
        }

        $sections = [];
        foreach ($this->sections as $section) {
            $sections[] = new TimelineSection($section['title'], $this->nonEmptyEvents($section['title'], $section['events']));
        }

        return new TimelineDiagram($this->nonEmptySections($sections), $this->title);
    }

    /**
     * @param list<TimelineEvent> $events
     *
     * @return non-empty-list<TimelineEvent>
     */
    private function nonEmptyEvents(string $sectionTitle, array $events): array
    {
        if ([] === $events) {
            throw new InvalidArgumentException(\sprintf('Timeline section "%s" must contain at least one event.', $sectionTitle));
        }

        return $events;
    }

    /**
     * @param list<TimelineSection> $sections
     *
     * @return non-empty-list<TimelineSection>
     */
    private function nonEmptySections(array $sections): array
    {
        if ([] === $sections) {
            throw new InvalidArgumentException('Timeline diagram must contain at least one section.');
        }

        return $sections;
    }
}
