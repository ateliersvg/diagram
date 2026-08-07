<?php

declare(strict_types=1);

namespace Atelier\Diagram\Renderer\Svg;

use Atelier\Diagram\Exception\RuntimeException;
use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Renderer\RendererInterface;
use Atelier\Diagram\Scene\BackgroundPattern;
use Atelier\Diagram\Scene\CircleNode;
use Atelier\Diagram\Scene\GroupNode;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\OrientedTextNode;
use Atelier\Diagram\Scene\PathNode;
use Atelier\Diagram\Scene\PatternKind;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Scene\TextNode;
use Atelier\Diagram\Support\Decimal;
use Atelier\Layout\Text\FontWeight;
use Atelier\Svg\Document;
use Atelier\Svg\Dumper\CompactXmlDumper;
use Atelier\Svg\Element\Builder;
use Atelier\Svg\Element\Builder\PatternBuilder;
use Atelier\Svg\Element\Descriptive\DescElement;
use Atelier\Svg\Element\Descriptive\TitleElement;
use Atelier\Svg\Element\Shape\CircleElement;
use Atelier\Svg\Element\Shape\RectElement;

/**
 * Renders a Scene to SVG through atelier/svg.
 *
 * The mapping is mechanical: one Scene node, one SVG element. Use
 * renderToDocument() to post-process the result (optimizer, sanitizer)
 * before serializing. The only namespace allowed to use Atelier\Svg.
 */
final class SvgRenderer implements RendererInterface
{
    public function render(Scene $scene): string
    {
        return (new CompactXmlDumper())->dump($this->renderToDocument($scene));
    }

    public function renderToDocument(Scene $scene): Document
    {
        $builder = new Builder();
        $builder->svg($this->round($scene->width), $this->round($scene->height));
        $builder->attr('viewBox', \sprintf('0 0 %s %s', $this->format($scene->width), $this->format($scene->height)));
        $builder->attr('class', 'atelier-diagram');
        $builder->attr('data-renderer', 'atelier/diagram');

        $root = $builder->getSvg();
        if (null !== $scene->title || null !== $scene->description) {
            $root->setAttribute('role', 'img');
            if (null !== $scene->title) {
                $title = new TitleElement();
                $title->setContent($scene->title);
                $root->appendChild($title);
            }
            if (null !== $scene->description) {
                $description = new DescElement();
                $description->setContent($scene->description);
                $root->appendChild($description);
            }
        }

        if (null !== $scene->backgroundColor) {
            $builder->rect(0, 0, $this->round($scene->width), $this->round($scene->height));
            $builder->attr('fill', $scene->backgroundColor);
            $builder->end();
        }

        if (null !== $scene->backgroundPattern) {
            $this->renderBackgroundPattern($builder, $scene->width, $scene->height, $scene->backgroundPattern);
        }

        foreach ($scene->nodes as $node) {
            $this->renderNode($builder, $node);
        }

        return $builder->getDocument();
    }

    /**
     * Emits the background pattern: a `<pattern>` in defs plus a full-canvas
     * rect referencing it, painted behind the nodes. An optional major layer
     * is drawn on top of the minor one.
     */
    private function renderBackgroundPattern(Builder $builder, float $width, float $height, BackgroundPattern $pattern): void
    {
        $baseId = 'adi-bg-'.$pattern->kind->value;
        $this->buildPatternLayer($builder->getDocument(), $baseId, $pattern->kind, $pattern->color, $pattern->size, $pattern->lineWidth, $pattern->opacity);
        $this->fillCanvasWithPattern($builder, $width, $height, $baseId);

        if ($pattern->hasMajor()) {
            $majorId = $baseId.'-major';
            $this->buildPatternLayer($builder->getDocument(), $majorId, $pattern->kind, (string) $pattern->majorColor, $pattern->majorSize, $pattern->majorLineWidth, $pattern->majorOpacity);
            $this->fillCanvasWithPattern($builder, $width, $height, $majorId);
        }
    }

    private function buildPatternLayer(Document $document, string $id, PatternKind $kind, string $color, float $cell, float $lineWidth, ?float $opacity): void
    {
        $pattern = PatternBuilder::create($document, $id)
            ->size($this->round($cell), $this->round($cell))
            ->units('userSpaceOnUse');

        if (PatternKind::Dots === $kind) {
            $dot = new CircleElement();
            $dot->setCx($this->format($cell / 2.0));
            $dot->setCy($this->format($cell / 2.0));
            $dot->setR($this->format($lineWidth));
            $this->stylePatternShape($dot, $color, $opacity);
            $pattern->addElement($dot);
        } else {
            $vertical = new RectElement();
            $vertical->setX('0')->setY('0')->setWidth($this->format($lineWidth))->setHeight($this->format($cell));
            $this->stylePatternShape($vertical, $color, $opacity);
            $pattern->addElement($vertical);

            $horizontal = new RectElement();
            $horizontal->setX('0')->setY('0')->setWidth($this->format($cell))->setHeight($this->format($lineWidth));
            $this->stylePatternShape($horizontal, $color, $opacity);
            $pattern->addElement($horizontal);
        }

        $pattern->addToDefs();
    }

    private function stylePatternShape(RectElement|CircleElement $shape, string $color, ?float $opacity): void
    {
        $shape->setAttribute('fill', $color);
        if (null !== $opacity) {
            $shape->setAttribute('opacity', $this->format($opacity));
        }
    }

    private function fillCanvasWithPattern(Builder $builder, float $width, float $height, string $id): void
    {
        $builder->rect(0, 0, $this->round($width), $this->round($height));
        $builder->attr('fill', \sprintf('url(#%s)', $id));
        $builder->end();
    }

    private function renderNode(Builder $builder, NodeInterface $node): void
    {
        if ($node instanceof GroupNode) {
            $builder->g();
            if (null !== $node->opacity) {
                $builder->attr('opacity', $this->format($node->opacity));
            }
            foreach ($node->children as $child) {
                $this->renderNode($builder, $child);
            }
            $builder->end();

            return;
        }

        if ($node instanceof RectNode) {
            $rx = $node->cornerRadius > 0.0 ? $this->round($node->cornerRadius) : null;
            $builder->rect($this->round($node->x), $this->round($node->y), $this->round($node->width), $this->round($node->height), $rx);
            $this->applyShapeStyle($builder, $node->style);
            $builder->end();

            return;
        }

        if ($node instanceof CircleNode) {
            $builder->circle($this->round($node->cx), $this->round($node->cy), $this->round($node->r));
            $this->applyShapeStyle($builder, $node->style);
            $builder->end();

            return;
        }

        if ($node instanceof LineNode) {
            $builder->line($this->round($node->x1), $this->round($node->y1), $this->round($node->x2), $this->round($node->y2));
            $this->applyShapeStyle($builder, $node->style);
            $builder->end();

            return;
        }

        if ($node instanceof PathNode) {
            $builder->path();
            $builder->attr('d', $node->data);
            $this->applyShapeStyle($builder, $node->style);
            $builder->end();

            return;
        }

        if ($node instanceof OrientedTextNode) {
            $x = $this->round($node->x);
            $y = $this->round($node->y);
            $builder->text($x, $y, $node->text);
            $this->applyTextStyle($builder, $node->style);
            if (abs($node->rotationDegrees) > 0.001) {
                $builder->attr('transform', \sprintf('rotate(%s %s %s)', $this->format($node->rotationDegrees), $this->format($x), $this->format($y)));
            }
            if (null !== $node->outlineColor && $node->outlineWidth > 0.0) {
                $builder->attr('stroke', $node->outlineColor);
                $builder->attr('stroke-width', $this->format($node->outlineWidth));
                $builder->attr('stroke-linejoin', 'round');
                $builder->attr('paint-order', 'stroke');
            }
            $builder->end();

            return;
        }

        if ($node instanceof TextNode) {
            $builder->text($this->round($node->x), $this->round($node->y), $node->text);
            $this->applyTextStyle($builder, $node->style);
            $builder->end();

            return;
        }

        throw new RuntimeException(\sprintf('SvgRenderer cannot render scene node of type %s.', $node::class));
    }

    private function applyShapeStyle(Builder $builder, ShapeStyle $style): void
    {
        $builder->attr('fill', $style->fill ?? 'none');

        if (null !== $style->stroke) {
            $builder->attr('stroke', $style->stroke);
            if (1.0 !== $style->strokeWidth) {
                $builder->attr('stroke-width', $this->format($style->strokeWidth));
            }
            if (null !== $style->strokeLineCap) {
                $builder->attr('stroke-linecap', $style->strokeLineCap->value);
            }
            if (null !== $style->strokeLineJoin) {
                $builder->attr('stroke-linejoin', $style->strokeLineJoin->value);
            }

            $dash = match ($style->lineStyle) {
                LineStyle::Solid => null,
                LineStyle::Dashed => [6.0, 4.0],
                LineStyle::Dotted => [2.0, 3.0],
            };
            if (null !== $dash) {
                $builder->attr('stroke-dasharray', \sprintf(
                    '%s %s',
                    $this->format($dash[0] * $style->strokeWidth),
                    $this->format($dash[1] * $style->strokeWidth),
                ));
            }
        }

        if (null !== $style->opacity) {
            $builder->attr('opacity', $this->format($style->opacity));
        }
    }

    private function applyTextStyle(Builder $builder, TextStyle $style): void
    {
        $builder->attr('font-family', $style->fontFamily);
        $builder->attr('font-size', $this->format($style->fontSize));
        if (FontWeight::Bold === $style->fontWeight) {
            $builder->attr('font-weight', 'bold');
        }
        if (TextAnchor::Start !== $style->anchor) {
            $builder->attr('text-anchor', TextAnchor::Middle === $style->anchor ? 'middle' : 'end');
        }
        $builder->attr('fill', $style->fill);
    }

    /**
     * Rounds a coordinate to 2 decimals.
     */
    private function round(float $value): float
    {
        return Decimal::round($value);
    }

    /**
     * Formats a number rounded to 2 decimals, without trailing zeros.
     */
    private function format(float $value): string
    {
        return Decimal::format($value);
    }
}
