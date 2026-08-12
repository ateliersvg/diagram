<?php

declare(strict_types=1);

use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\GroupNode;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Theme\Theme;
use Atelier\Layout\Text\FontWeight;

/**
 * Builds the renderer smoke Scene: every node type, both style kinds,
 * all three line styles, all three text anchors, both font weights.
 *
 * Shared between examples/render-smoke.php and the renderer snapshot test.
 */
function buildSmokeScene(): Scene
{
    $strokeWidth = Theme::default()->strokeWidth;

    $box = new ShapeStyle(fill: '#f1f5f9', stroke: '#334155', strokeWidth: $strokeWidth);
    $edge = ShapeStyle::stroked('#334155', $strokeWidth);
    $dashed = new ShapeStyle(stroke: '#64748b', strokeWidth: $strokeWidth, lineStyle: LineStyle::Dashed);
    $dotted = new ShapeStyle(stroke: '#64748b', strokeWidth: $strokeWidth, lineStyle: LineStyle::Dotted);

    $label = new TextStyle('Helvetica, Arial, sans-serif', 14.0, FontWeight::Bold, TextAnchor::Middle, '#1e293b');
    $muted = new TextStyle('Helvetica, Arial, sans-serif', 11.0, FontWeight::Normal, TextAnchor::Middle, '#64748b');
    $start = new TextStyle('Helvetica, Arial, sans-serif', 11.0, fill: '#64748b');
    $end = new TextStyle('Helvetica, Arial, sans-serif', 11.0, anchor: TextAnchor::End, fill: '#94a3b8');

    return new Scene(420.0, 260.0, '#ffffff', [
        // Two state boxes with centered bold labels.
        new RectNode(40.0, 40.0, 120.0, 48.0, $box, cornerRadius: 8.0),
        new TextNode(100.0, 69.0, 'Idle', $label),
        new RectNode(260.0, 40.0, 120.0, 48.0, $box, cornerRadius: 8.0),
        new TextNode(320.0, 69.0, 'Running', $label),

        // Transition: solid line + filled-triangle arrowhead path + label.
        new LineNode(160.0, 64.0, 252.0, 64.0, $edge),
        new PathNode('M 252 58.5 L 262 64 L 252 69.5 Z', ShapeStyle::filled('#334155')),
        new TextNode(210.0, 54.0, 'start', $muted),

        // Dashed and dotted droplines.
        new LineNode(100.0, 88.0, 100.0, 156.0, $dashed),
        new LineNode(320.0, 88.0, 320.0, 156.0, $dotted),

        // Filled circles joined by a stroked cubic path.
        new CircleNode(100.0, 176.0, 14.0, new ShapeStyle(fill: '#2563eb', stroke: '#1e40af')),
        new CircleNode(320.0, 176.0, 14.0, new ShapeStyle(fill: '#dc2626', opacity: 0.85)),
        new PathNode('M 114 176 C 180 126 240 226 306 176', ShapeStyle::stroked('#16a34a', 2.0)),

        // Legend group with shared opacity.
        new GroupNode([
            new RectNode(40.0, 222.0, 12.0, 12.0, ShapeStyle::filled('#2563eb')),
            new TextNode(58.0, 232.0, 'queue', $start),
        ], opacity: 0.9),

        // End-anchored caption.
        new TextNode(404.0, 244.0, 'atelier/diagram', $end),
    ]);
}
