<?php

declare(strict_types=1);

namespace Atelier\Diagram\Venn;

use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;

/**
 * Fluent builder for Venn diagrams.
 *
 * Sets receive ids A, B, C in declaration order; region labels address
 * them by id combination (A, B, C, AB, AC, BC, ABC). Region labels may
 * be declared before the sets they reference -- references are checked
 * on build().
 *
 *     $diagram = (new VennDiagramBuilder())
 *         ->title('Skills')
 *         ->set('Frontend')
 *         ->set('Backend')
 *         ->regionLabel('AB', 'HTTP')
 *         ->build();
 */
final class VennDiagramBuilder
{
    private const array SET_IDS = ['A', 'B', 'C'];

    /** @var list<VennSet> */
    private array $sets = [];

    /** @var array<string, Label> */
    private array $regionLabels = [];

    private ?Title $title = null;

    private bool $showLegend = false;

    private ?float $targetWidth = null;

    private ?float $targetHeight = null;

    private float $paddingPercent = 0.0;

    private float $innerPaddingPercent = 0.0;

    private float $circleStrokeWidth = 0.0;

    /**
     * Adds a set. The first call defines set A, the second B, the third C.
     */
    public function set(string $label, ?int $cardinality = null): self
    {
        if (\count($this->sets) >= \count(self::SET_IDS)) {
            throw new InvalidDiagramException(\sprintf('A Venn diagram supports at most 3 sets, cannot add set "%s".', $label));
        }

        $this->sets[] = new VennSet(self::SET_IDS[\count($this->sets)], $label, $cardinality);

        return $this;
    }

    /**
     * Labels a region. Last call wins for a given region.
     *
     * @param string $region one of A, B, C, AB, AC, BC, ABC
     */
    public function regionLabel(string $region, string $text): self
    {
        if (!\in_array($region, VennDiagram::REGIONS, true)) {
            throw new InvalidDiagramException(\sprintf('Unknown Venn region "%s", expected one of %s.', $region, implode(', ', VennDiagram::REGIONS)));
        }

        $this->regionLabels[$region] = new Label($text);

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = new Title($title);

        return $this;
    }

    /**
     * Adds a set -> color legend below the diagram (disabled by default).
     */
    public function withLegend(): self
    {
        $this->showLegend = true;

        return $this;
    }

    public function targetSize(float $width, float $height): self
    {
        $this->targetWidth = $width;
        $this->targetHeight = $height;

        return $this;
    }

    public function paddingPercent(float $percent): self
    {
        $this->paddingPercent = $percent;

        return $this;
    }

    public function innerPaddingPercent(float $percent): self
    {
        $this->innerPaddingPercent = $percent;

        return $this;
    }

    public function circleStrokeWidth(float $strokeWidth): self
    {
        $this->circleStrokeWidth = $strokeWidth;

        return $this;
    }

    public function build(): VennDiagram
    {
        return new VennDiagram(
            $this->sets,
            $this->title,
            $this->regionLabels,
            $this->showLegend,
            $this->targetWidth,
            $this->targetHeight,
            $this->paddingPercent,
            $this->innerPaddingPercent,
            $this->circleStrokeWidth,
        );
    }
}
