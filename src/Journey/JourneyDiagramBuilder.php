<?php

declare(strict_types=1);

namespace Atelier\Diagram\Journey;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Model\Title;

final class JourneyDiagramBuilder
{
    /**
     * @var list<array{title: string, tasks: list<JourneyTask>}>
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
            throw new InvalidArgumentException('Journey section title must be non-empty.');
        }

        $this->sections[] = ['title' => $title, 'tasks' => []];

        return $this;
    }

    /**
     * @param non-empty-list<string> $actors
     */
    public function task(string $text, int $score, array $actors): self
    {
        if ([] === $this->sections) {
            throw new InvalidArgumentException('Journey task must belong to a section.');
        }

        $this->sections[\array_key_last($this->sections)]['tasks'][] = new JourneyTask(
            $text,
            $score,
            array_map(static fn (string $actor): JourneyActor => new JourneyActor($actor), $actors),
        );

        return $this;
    }

    public function build(): JourneyDiagram
    {
        if ([] === $this->sections) {
            throw new InvalidArgumentException('Journey diagram must contain at least one section.');
        }

        $sections = [];
        foreach ($this->sections as $section) {
            $sections[] = new JourneySection($section['title'], $this->nonEmptyTasks($section['title'], $section['tasks']));
        }

        return new JourneyDiagram($this->nonEmptySections($sections), $this->title);
    }

    /**
     * @param list<JourneyTask> $tasks
     *
     * @return non-empty-list<JourneyTask>
     */
    private function nonEmptyTasks(string $sectionTitle, array $tasks): array
    {
        if ([] === $tasks) {
            throw new InvalidArgumentException(\sprintf('Journey section "%s" must contain at least one task.', $sectionTitle));
        }

        return $tasks;
    }

    /**
     * @param list<JourneySection> $sections
     *
     * @return non-empty-list<JourneySection>
     */
    private function nonEmptySections(array $sections): array
    {
        if ([] === $sections) {
            throw new InvalidArgumentException('Journey diagram must contain at least one section.');
        }

        return $sections;
    }
}
