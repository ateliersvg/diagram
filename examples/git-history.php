<?php

declare(strict_types=1);

use Atelier\Diagram\Git\GitGraph;
use Atelier\Diagram\Git\GitGraphBuilder;

/**
 * The section-2.3 reference history: main plus two feature branches,
 * interleaved commits, two merges, two tags.
 *
 * buildGitHistory() (untitled) and gitHistoryMermaid() describe the same
 * history, so builder and parser must render byte-identical SVG. That
 * equivalence is frozen by tests/Parser/GitGraphParserTest.php. The titled
 * builder rendering is frozen by the snapshot in
 * tests/Layout/Git/__snapshots__/git-graph.svg (the gitGraph grammar has
 * no title statement, so the title only exists on the builder path).
 */
function buildGitHistory(?string $title = null): GitGraph
{
    $builder = new GitGraphBuilder();
    if (null !== $title) {
        $builder->title($title);
    }

    return $builder
        ->commit('a1b2c3d')
        ->commit()
        ->branch('feature-auth')
        ->commit()
        ->commit(tag: 'auth-beta')
        ->checkout('main')
        ->commit()
        ->branch('feature-ui')
        ->commit()
        ->checkout('main')
        ->merge('feature-auth')
        ->checkout('feature-ui')
        ->commit()
        ->checkout('main')
        ->merge('feature-ui')
        ->commit('f9e8d7c', 'v1.0')
        ->build();
}

/**
 * Mermaid source equivalent to buildGitHistory().
 */
function gitHistoryMermaid(): string
{
    return <<<'MERMAID'
        %% section-2.3 reference history
        gitGraph LR:
            commit id: "a1b2c3d"
            commit
            branch feature-auth
            commit
            commit tag: "auth-beta"
            checkout main
            commit
            branch feature-ui
            commit
            checkout main
            merge feature-auth
            switch feature-ui
            commit
            checkout main
            merge feature-ui
            commit tag: "v1.0" id: "f9e8d7c"
        MERMAID;
}
