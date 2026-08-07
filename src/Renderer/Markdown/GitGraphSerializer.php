<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Markdown;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Git\Branch;
use Atelier\Diagram\Git\Commit;
use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Git\GitGraphBuilder;
use Atelier\Diagram\Model\Direction;

/**
 * Serializes a GitGraph to canonical Mermaid gitGraph source.
 *
 * The model stores branches and commits, not the original statement
 * sequence, so the serializer reconstructs one: it replays the commits in
 * operation order, simulating branch tips, and emits `branch` statements
 * as soon as a branch's creation point (its createdAt commit) has been
 * replayed, plus `checkout` statements whenever the current branch must
 * change. The emitted sequence is canonical, not the user's original --
 * equivalence is by round-trip: parsing it back yields the same model
 * (same branches, commits, parents, tips).
 *
 * Commit ids equal to the GitGraphBuilder auto-id sequence are omitted so
 * that re-parsing regenerates them; this also keeps the ids of merge
 * commits stable, since the `merge` statement cannot carry an explicit id.
 *
 * The title and the legend flag are not serialized: the supported grammar
 * has no statement for them. A graph that cannot be expressed in the
 * parser subset (initial branch not named "main", merge commit with a tag
 * or an id outside the auto-id sequence, parents inconsistent with replay
 * tips, ...) throws an InvalidArgumentException.
 *
 * @internal used by MarkdownRenderer
 */
final class GitGraphSerializer
{
    /**
     * @var list<string>
     */
    private array $lines = [];

    /**
     * Simulated tip commit id per created branch, null when commitless.
     *
     * @var array<string, string|null>
     */
    private array $tips = [];

    /**
     * Branches not yet created in the replay, in creation order.
     *
     * @var list<Branch>
     */
    private array $pending = [];

    /**
     * @var array<string, Commit>
     */
    private array $commitsById = [];

    /**
     * @var array<string, true>
     */
    private array $emitted = [];

    private string $current = GitGraphBuilder::INITIAL_BRANCH;

    private int $autoIdCount = 0;

    public function serialize(GitGraph $graph): string
    {
        $initial = $graph->branches[0] ?? null;
        if (null === $initial || GitGraphBuilder::INITIAL_BRANCH !== $initial->name || null !== $initial->createdAt) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: the first branch must be the initial "%s" branch.', GitGraphBuilder::INITIAL_BRANCH));
        }

        $this->lines = [Direction::TopToBottom === $graph->direction ? 'gitGraph TB:' : 'gitGraph'];
        $this->tips = [$initial->name => null];
        $this->pending = \array_slice($graph->branches, 1);
        $this->commitsById = [];
        $this->emitted = [];
        $this->current = $initial->name;
        $this->autoIdCount = 0;

        foreach ($graph->branches as $branch) {
            $this->assertSerializableBranch($branch);
        }
        foreach ($graph->commits as $commit) {
            $this->commitsById[$commit->id] = $commit;
        }

        $this->createReadyBranches();

        foreach ($graph->commits as $commit) {
            if (isset($this->emitted[$commit->id])) {
                throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: commit id "%s" is used by two commits.', $commit->id));
            }
            if (!\array_key_exists($commit->branch, $this->tips)) {
                throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: commit "%s" sits on branch "%s", which is not created at that point of the history.', $commit->id, $commit->branch));
            }

            $this->checkout($commit->branch);

            if ($commit->isMerge()) {
                $this->emitMerge($commit);
            } else {
                $this->emitCommit($commit);
            }

            $this->tips[$commit->branch] = $commit->id;
            $this->emitted[$commit->id] = true;
            $this->createReadyBranches();
        }

        if ([] !== $this->pending) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: branch "%s" is created from commit "%s", which does not exist.', $this->pending[0]->name, (string) $this->pending[0]->createdAt));
        }

        return implode("\n", $this->lines)."\n";
    }

    /**
     * Creates pending branches, in creation order, as soon as their
     * creation point has been replayed (immediately for branches created
     * before any commit).
     */
    private function createReadyBranches(): void
    {
        while ([] !== $this->pending) {
            $branch = $this->pending[0];
            if (null !== $branch->createdAt && !isset($this->emitted[$branch->createdAt])) {
                return;
            }

            // `branch` forks from the current tip: stand on a branch whose
            // tip is the creation point.
            if ($this->tips[$this->current] !== $branch->createdAt) {
                $this->checkout($this->findBranchWithTip($branch->createdAt, $branch->name));
            }

            $this->lines[] = '    branch '.$branch->name;
            $this->tips[$branch->name] = $branch->createdAt;
            $this->current = $branch->name;
            array_shift($this->pending);
        }
    }

    private function emitCommit(Commit $commit): void
    {
        $parent = $commit->parents[0] ?? null;
        if (\count($commit->parents) > 1 || $this->tips[$commit->branch] !== $parent) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: the parents of commit "%s" do not match the tip of branch "%s" at that point of the history.', $commit->id, $commit->branch));
        }

        $line = '    commit';
        if ($commit->id === $this->nextAutoId()) {
            ++$this->autoIdCount;
        } else {
            $this->assertSerializableValue($commit->id, \sprintf('id of commit "%s"', $commit->id));
            $line .= \sprintf(' id: "%s"', $commit->id);
        }
        if (null !== $commit->tag) {
            $this->assertSerializableValue($commit->tag, \sprintf('tag of commit "%s"', $commit->id));
            $line .= \sprintf(' tag: "%s"', $commit->tag);
        }

        $this->lines[] = $line;
    }

    private function emitMerge(Commit $commit): void
    {
        if (2 !== \count($commit->parents) || null !== $commit->tag) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: merge commit "%s" must have exactly two parents and no tag.', $commit->id));
        }
        if ($this->tips[$commit->branch] !== $commit->parents[0]) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: the first parent of merge commit "%s" is not the tip of branch "%s" at that point of the history.', $commit->id, $commit->branch));
        }
        if ($commit->id !== $this->nextAutoId()) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: the id of merge commit "%s" cannot be expressed -- the merge statement only carries auto-generated ids.', $commit->id));
        }

        $this->lines[] = '    merge '.$this->findBranchWithTip($commit->parents[1], $commit->branch);
        ++$this->autoIdCount;
    }

    /**
     * Finds a created branch (other than $exclude) whose simulated tip is
     * $tip, preferring the branch the tip commit was made on.
     */
    private function findBranchWithTip(?string $tip, string $exclude): string
    {
        if (null !== $tip) {
            $owner = ($this->commitsById[$tip] ?? null)?->branch;
            if (null !== $owner && $owner !== $exclude && ($this->tips[$owner] ?? false) === $tip) {
                return $owner;
            }
        }

        foreach ($this->tips as $name => $candidate) {
            if ($candidate === $tip && $name !== $exclude) {
                return $name;
            }
        }

        throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: no branch has commit "%s" as tip when it is needed as a fork or merge point.', $tip ?? '(none)'));
    }

    private function checkout(string $branch): void
    {
        if ($branch !== $this->current) {
            $this->lines[] = '    checkout '.$branch;
            $this->current = $branch;
        }
    }

    /**
     * The next id GitGraphBuilder::nextAutoId() would generate on re-parse.
     * Must mirror that (private, deterministic) sequence exactly.
     */
    private function nextAutoId(): string
    {
        return substr(md5('atelier-diagram-commit-'.($this->autoIdCount + 1)), 0, 7);
    }

    private function assertSerializableBranch(Branch $branch): void
    {
        if ('' === $branch->name || 1 === preg_match('/\s/', $branch->name)) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: branch name "%s" must be non-empty and contain no whitespace.', $branch->name));
        }
    }

    private function assertSerializableValue(string $value, string $what): void
    {
        if (str_contains($value, '"') || 1 === preg_match('/[\r\n]/', $value)) {
            throw new InvalidArgumentException(\sprintf('Cannot render git graph to Mermaid: the %s must not contain quotes or newlines.', $what));
        }
    }
}
