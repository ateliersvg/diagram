<?php

declare(strict_types=1);

namespace Atelier\Diagram\Git;

use Atelier\Diagram\Exception\InvalidDiagramException;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Model\Title;

/**
 * Fluent builder mirroring git semantics.
 *
 * History starts on the `main` branch. `branch()` creates a new branch at
 * the current tip and checks it out (like `git checkout -b`); `merge()`
 * records a merge commit with two parents. Auto-generated commit ids are
 * deterministic, so identical operation sequences build identical graphs.
 *
 * ```php
 * $graph = (new GitGraphBuilder())
 *     ->commit()
 *     ->branch('feature')
 *     ->commit(tag: 'v0.1')
 *     ->checkout('main')
 *     ->merge('feature')
 *     ->build();
 * ```
 */
final class GitGraphBuilder
{
    public const string INITIAL_BRANCH = 'main';

    /**
     * @var array<string, Branch> branches keyed by name, in creation order
     */
    private array $branches;

    /**
     * @var list<Commit> commits in operation order
     */
    private array $commits = [];

    /**
     * @var array<string, string|null> tip commit id per branch, null when
     *                                 the branch has no commit yet
     */
    private array $tips;

    private string $currentBranch = self::INITIAL_BRANCH;

    private Direction $direction = Direction::LeftToRight;

    private ?Title $title = null;

    private bool $showLegend = true;

    private int $autoIdSequence = 0;

    public function __construct()
    {
        $this->branches = [self::INITIAL_BRANCH => new Branch(self::INITIAL_BRANCH)];
        $this->tips = [self::INITIAL_BRANCH => null];
    }

    /**
     * Sets the flow direction of the commit axis.
     */
    public function direction(Direction $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    /**
     * Sets the diagram title.
     */
    public function title(string $title): self
    {
        $this->title = new Title($title);

        return $this;
    }

    /**
     * Disables the automatic branch -> color legend (enabled by default).
     */
    public function withoutLegend(): self
    {
        $this->showLegend = false;

        return $this;
    }

    /**
     * Records a commit on the current branch.
     *
     * @param string|null $id  commit id, auto-generated (deterministic short
     *                         hash) when omitted
     * @param string|null $tag tag to attach to the commit
     */
    public function commit(?string $id = null, ?string $tag = null): self
    {
        $id ??= $this->nextAutoId();

        if ($this->hasCommit($id)) {
            throw new InvalidDiagramException(\sprintf('Commit id "%s" is already used by another commit.', $id));
        }

        $parent = $this->tips[$this->currentBranch];
        $this->commits[] = new Commit($id, $this->currentBranch, $tag, null === $parent ? [] : [$parent]);
        $this->tips[$this->currentBranch] = $id;

        return $this;
    }

    /**
     * Creates a branch at the current tip and checks it out.
     */
    public function branch(string $name): self
    {
        if (isset($this->branches[$name])) {
            throw new InvalidDiagramException(\sprintf('Branch "%s" already exists.', $name));
        }

        $this->branches[$name] = new Branch($name, $this->tips[$this->currentBranch]);
        $this->tips[$name] = $this->tips[$this->currentBranch];
        $this->currentBranch = $name;

        return $this;
    }

    /**
     * Switches the current branch.
     */
    public function checkout(string $name): self
    {
        if (!isset($this->branches[$name])) {
            throw new InvalidDiagramException(\sprintf('Cannot checkout unknown branch "%s".', $name));
        }

        $this->currentBranch = $name;

        return $this;
    }

    /**
     * Merges the named branch into the current branch.
     *
     * Records a merge commit on the current branch with two parents: the
     * current tip and the tip of the merged branch.
     */
    public function merge(string $name): self
    {
        if (!isset($this->branches[$name])) {
            throw new InvalidDiagramException(\sprintf('Cannot merge unknown branch "%s".', $name));
        }
        if ($name === $this->currentBranch) {
            throw new InvalidDiagramException(\sprintf('Cannot merge branch "%s" into itself.', $name));
        }

        $theirs = $this->tips[$name];
        if (null === $theirs) {
            throw new InvalidDiagramException(\sprintf('Cannot merge branch "%s": it has no commits.', $name));
        }

        $ours = $this->tips[$this->currentBranch];
        if (null === $ours) {
            throw new InvalidDiagramException(\sprintf('Cannot merge into branch "%s": it has no commits.', $this->currentBranch));
        }

        $id = $this->nextAutoId();
        $this->commits[] = new Commit($id, $this->currentBranch, null, [$ours, $theirs]);
        $this->tips[$this->currentBranch] = $id;

        return $this;
    }

    /**
     * Builds the immutable graph.
     */
    public function build(): GitGraph
    {
        return new GitGraph($this->direction, array_values($this->branches), $this->commits, $this->title, $this->showLegend);
    }

    /**
     * Generates a deterministic 7-character hex commit id.
     */
    private function nextAutoId(): string
    {
        return substr(md5('atelier-diagram-commit-'.++$this->autoIdSequence), 0, 7);
    }

    private function hasCommit(string $id): bool
    {
        foreach ($this->commits as $commit) {
            if ($commit->id === $id) {
                return true;
            }
        }

        return false;
    }
}
