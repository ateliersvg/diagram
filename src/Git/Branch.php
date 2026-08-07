<?php

declare(strict_types=1);

namespace Atelier\Diagram\Git;

/**
 * One branch in a git graph.
 */
final readonly class Branch
{
    /**
     * @param string      $name      branch name
     * @param string|null $createdAt id of the commit the branch was created
     *                               from, null when the branch exists from
     *                               the start of history (the initial branch,
     *                               or a branch created before any commit)
     */
    public function __construct(
        public string $name,
        public ?string $createdAt = null,
    ) {
    }
}
