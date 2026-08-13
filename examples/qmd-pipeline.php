<?php

declare(strict_types=1);

/*
 * QMD Hybrid Search Pipeline as a top-down flowchart.
 *
 * Shows how an ASCII box-and-arrow diagram maps onto FlowchartBuilder:
 * every box is ->node(), every arrow is ->edge(), fan-in is just several
 * edges pointing at one node. Node labels are single-line (the flowchart
 * layout measures one line per node), so dense parameter boxes are
 * condensed and the detail lives in edge labels.
 */

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Theme\Theme;

require dirname(__DIR__).'/vendor/autoload.php';

$model = Diagram::flowchart()
    ->direction(Direction::TopToBottom)
    ->title('QMD Hybrid Search Pipeline')

    // entry + the two-way split
    ->node('Q', 'User Query')
    ->node('QE', 'Query Expansion (fine-tuned)')
    ->node('OQ', 'Original Query (x2 weight)')
    ->edge('Q', 'QE')
    ->edge('Q', 'OQ')

    // three concrete queries
    ->node('O1', 'Original Query')
    ->node('E1', 'Expanded Query 1')
    ->node('E2', 'Expanded Query 2')
    ->edge('OQ', 'O1')
    ->edge('QE', 'E1', '2 alternatives')
    ->edge('QE', 'E2')

    // each query fans out to BM25 + Vector
    ->node('B1', 'BM25 / FTS5')
    ->node('V1', 'Vector Search')
    ->node('B2', 'BM25 / FTS5')
    ->node('V2', 'Vector Search')
    ->node('B3', 'BM25 / FTS5')
    ->node('V3', 'Vector Search')
    ->edge('O1', 'B1')
    ->edge('O1', 'V1')
    ->edge('E1', 'B2')
    ->edge('E1', 'V2')
    ->edge('E2', 'B3')
    ->edge('E2', 'V3')

    // fan-in: six retrievers converge on fusion
    ->node('RRF', 'RRF Fusion + Bonus (top 30)')
    ->edge('B1', 'RRF')
    ->edge('V1', 'RRF')
    ->edge('B2', 'RRF')
    ->edge('V2', 'RRF')
    ->edge('B3', 'RRF')
    ->edge('V3', 'RRF')

    // rerank + blend
    ->node('RR', 'LLM Re-ranking (qwen3-reranker)')
    ->node('BL', 'Position-Aware Blend')
    ->edge('RRF', 'RR', 'orig x2, +0.05 top-rank')
    ->edge('RR', 'BL', 'Yes/No + logprobs')

    // group the six retrievers into one cluster
    ->subgraph('retrieval', 'Hybrid Retrieval', ['B1', 'V1', 'B2', 'V2', 'B3', 'V3'])

    ->build();

$outputDir = __DIR__.'/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Cannot create output directory: '.$outputDir);
}

$target = $outputDir.'/qmd-pipeline.svg';
Diagram::of($model)->saveSvg($target, Theme::dark());

echo 'Wrote '.$target.\PHP_EOL;
echo 'Mermaid round-trip:'.\PHP_EOL.\PHP_EOL;
echo Diagram::of($model)->toMermaid().\PHP_EOL;
