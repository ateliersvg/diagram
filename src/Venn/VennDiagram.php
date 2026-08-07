<?php

declare(strict_types=1);

namespace Atelier\Diagram\Venn;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Model\Label;
use Atelier\Diagram\Model\Title;

/**
 * Venn diagram of 2 or 3 sets with optional title and region labels.
 *
 * Regions are addressed by the ids of the sets they intersect: A, B, C
 * for the exclusive regions, AB, AC, BC for the pairwise intersections,
 * ABC for the center.
 */
final readonly class VennDiagram implements DiagramModel
{
    /**
     * Every addressable region of a 3-set diagram, in canonical key order.
     */
    public const array REGIONS = ['A', 'B', 'C', 'AB', 'AC', 'BC', 'ABC'];

    private const array SET_IDS = ['A', 'B', 'C'];

    /**
     * @param list<VennSet>        $sets         2 or 3 sets, ids A, B, C in order
     * @param Title|null           $title        optional diagram title
     * @param array<string, Label> $regionLabels labels keyed by region (subset of REGIONS)
     * @param bool                 $showLegend   whether layout adds a set -> color legend
     */
    public function __construct(
        public array $sets,
        public ?Title $title = null,
        public array $regionLabels = [],
        public bool $showLegend = false,
        public ?float $targetWidth = null,
        public ?float $targetHeight = null,
        public float $paddingPercent = 0.0,
        public float $innerPaddingPercent = 0.0,
        public float $circleStrokeWidth = 0.0,
    ) {
        $count = \count($sets);
        if ($count < 2 || $count > 3) {
            throw new InvalidDiagramException(\sprintf('A Venn diagram needs 2 or 3 sets, got %d.', $count));
        }

        $ids = [];
        foreach ($sets as $index => $set) {
            if (self::SET_IDS[$index] !== $set->id) {
                throw new InvalidDiagramException(\sprintf('Venn set #%d must have id "%s", got "%s".', $index + 1, self::SET_IDS[$index], $set->id));
            }
            $ids[] = $set->id;
        }

        foreach (array_keys($regionLabels) as $region) {
            if (!\in_array($region, self::REGIONS, true)) {
                throw new InvalidDiagramException(\sprintf('Unknown Venn region "%s", expected one of %s.', $region, implode(', ', self::REGIONS)));
            }
            foreach (str_split($region) as $id) {
                if (!\in_array($id, $ids, true)) {
                    throw new InvalidDiagramException(\sprintf('Venn region "%s" references set "%s", but the diagram only defines sets %s.', $region, $id, implode(', ', $ids)));
                }
            }
        }

        if ((null === $targetWidth) !== (null === $targetHeight)) {
            throw new InvalidArgumentException('Venn target size needs both width and height.');
        }
        if ((null !== $targetWidth && $targetWidth <= 0.0) || (null !== $targetHeight && $targetHeight <= 0.0)) {
            throw new InvalidArgumentException('Venn target size dimensions must be positive.');
        }
        if ($paddingPercent < 0.0 || $innerPaddingPercent < 0.0) {
            throw new InvalidArgumentException('Venn padding percentages must not be negative.');
        }
        if ($circleStrokeWidth < 0.0) {
            throw new InvalidArgumentException('Venn circle stroke width must not be negative.');
        }
    }
}
