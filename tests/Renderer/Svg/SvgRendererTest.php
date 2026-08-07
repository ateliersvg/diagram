<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Svg;

use Atelier\Diagram\Exception\RuntimeException;
use Atelier\Diagram\Model\LineStyle;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use Atelier\Diagram\Scene\BackgroundPattern;
use Atelier\Diagram\Scene\GroupNode;
use Atelier\Diagram\Scene\LineNode;
use Atelier\Diagram\Scene\NodeInterface;
use Atelier\Diagram\Scene\OrientedTextNode;
use Atelier\Diagram\Scene\PatternKind;
use Atelier\Diagram\Scene\RectNode;
use Atelier\Diagram\Scene\Scene;
use Atelier\Diagram\Scene\Style\ShapeStyle;
use Atelier\Diagram\Scene\Style\StrokeLineCap;
use Atelier\Diagram\Scene\Style\StrokeLineJoin;
use Atelier\Diagram\Scene\Style\TextAnchor;
use Atelier\Diagram\Scene\Style\TextStyle;
use Atelier\Diagram\Tests\ExampleFixtures;
use Atelier\Svg\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SvgRenderer::class)]
final class SvgRendererTest extends TestCase
{
    use ExampleFixtures;

    public function testRenderMatchesFrozenSmokeSnapshot(): void
    {
        self::loadExample('smoke-scene.php');

        $svg = (new SvgRenderer())->render(buildSmokeScene());

        $snapshot = file_get_contents(__DIR__.'/__snapshots__/scene-smoke.svg');
        $this->assertNotFalse($snapshot);
        $this->assertSame(trim($snapshot), trim($svg));
    }

    public function testGridBackgroundPatternEmitsMinorAndMajorPatterns(): void
    {
        $scene = new Scene(
            200.0,
            160.0,
            '#06182b',
            [],
            backgroundPattern: new BackgroundPattern(
                PatternKind::Grid,
                '#67e8f9',
                size: 16.0,
                lineWidth: 0.45,
                opacity: 0.26,
                majorColor: '#e6fbff',
                majorSize: 80.0,
            ),
        );

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('<pattern', $svg);
        $this->assertStringContainsString('id="adi-bg-grid"', $svg);
        $this->assertStringContainsString('fill="url(#adi-bg-grid)"', $svg);
        $this->assertStringContainsString('id="adi-bg-grid-major"', $svg);
        $this->assertStringContainsString('fill="url(#adi-bg-grid-major)"', $svg);
    }

    public function testDotsBackgroundPatternEmitsASinglePattern(): void
    {
        $scene = new Scene(
            120.0,
            120.0,
            '#ffffff',
            [],
            backgroundPattern: new BackgroundPattern(PatternKind::Dots, '#94a3b8', size: 20.0, lineWidth: 1.5),
        );

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('id="adi-bg-dots"', $svg);
        $this->assertStringContainsString('fill="url(#adi-bg-dots)"', $svg);
        $this->assertStringNotContainsString('adi-bg-dots-major', $svg);
    }

    public function testSceneWithoutBackgroundPatternEmitsNoPattern(): void
    {
        $svg = (new SvgRenderer())->render(new Scene(100.0, 100.0, '#ffffff'));

        $this->assertStringNotContainsString('<pattern', $svg);
        $this->assertStringNotContainsString('url(#adi-bg', $svg);
    }

    public function testRenderToDocumentSetsRootDimensionsAndViewBox(): void
    {
        $document = (new SvgRenderer())->renderToDocument(new Scene(400.0, 300.0));

        $this->assertInstanceOf(Document::class, $document);
        $root = $document->getRootElement();
        $this->assertNotNull($root);
        $this->assertSame('400', $root->getAttribute('width'));
        $this->assertSame('300', $root->getAttribute('height'));
        $this->assertSame('0 0 400 300', $root->getAttribute('viewBox'));
        $this->assertSame('atelier-diagram', $root->getAttribute('class'));
        $this->assertSame('atelier/diagram', $root->getAttribute('data-renderer'));
    }

    public function testSceneMetadataBecomesTitleAndDescription(): void
    {
        $svg = (new SvgRenderer())->render(new Scene(
            100.0,
            50.0,
            title: 'Checkout flow',
            description: 'Flowchart rendered by atelier/diagram.',
        ));

        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringContainsString('<title>Checkout flow</title><desc>Flowchart rendered by atelier/diagram.</desc>', $svg);
    }

    public function testBackgroundBecomesFullSizeRect(): void
    {
        $svg = (new SvgRenderer())->render(new Scene(100.0, 50.0, '#fafafa'));

        $this->assertStringContainsString('<rect x="0" y="0" width="100" height="50" fill="#fafafa"/>', $svg);
    }

    public function testTransparentSceneHasNoBackgroundRect(): void
    {
        $svg = (new SvgRenderer())->render(new Scene(100.0, 50.0));

        $this->assertStringNotContainsString('<rect', $svg);
    }

    public function testNullFillSerializesAsNone(): void
    {
        $scene = new Scene(100.0, 100.0, null, [
            new LineNode(0.0, 0.0, 10.0, 10.0, ShapeStyle::stroked('#000')),
        ]);

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('fill="none" stroke="#000"', $svg);
    }

    public function testDashArraysScaleWithStrokeWidth(): void
    {
        $scene = new Scene(100.0, 100.0, null, [
            new LineNode(0.0, 0.0, 10.0, 0.0, new ShapeStyle(stroke: '#000', strokeWidth: 2.0, lineStyle: LineStyle::Dashed)),
            new LineNode(0.0, 5.0, 10.0, 5.0, new ShapeStyle(stroke: '#000', strokeWidth: 2.0, lineStyle: LineStyle::Dotted)),
        ]);

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('stroke-dasharray="12 8"', $svg);
        $this->assertStringContainsString('stroke-dasharray="4 6"', $svg);
    }

    public function testStrokeCapsAndJoinsAreSerializedWhenProvided(): void
    {
        $scene = new Scene(100.0, 100.0, null, [
            new LineNode(
                0.0,
                0.0,
                10.0,
                0.0,
                new ShapeStyle(
                    stroke: '#000',
                    strokeLineCap: StrokeLineCap::Square,
                    strokeLineJoin: StrokeLineJoin::Bevel,
                ),
            ),
        ]);

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('stroke-linecap="square"', $svg);
        $this->assertStringContainsString('stroke-linejoin="bevel"', $svg);
    }

    public function testCoordinatesAreRoundedToTwoDecimals(): void
    {
        $scene = new Scene(100.0, 100.0, null, [
            new LineNode(1.23456, 0.0, 10.005, 0.0, ShapeStyle::stroked('#000')),
        ]);

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('x1="1.23"', $svg);
        $this->assertStringContainsString('x2="10.01"', $svg);
    }

    public function testNestedGroupsCarryOpacity(): void
    {
        $scene = new Scene(100.0, 100.0, null, [
            new GroupNode([
                new GroupNode([
                    new RectNode(0.0, 0.0, 10.0, 10.0, ShapeStyle::filled('#fff')),
                ]),
            ], opacity: 0.5),
        ]);

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('<g opacity="0.5"><g><rect', $svg);
    }

    public function testOrientedTextSerializesRotationAndOutline(): void
    {
        $scene = new Scene(100.0, 100.0, null, [
            new OrientedTextNode(
                50.0,
                60.0,
                'approve',
                new TextStyle('Helvetica', 14.0, anchor: TextAnchor::Middle, fill: '#111'),
                26.57,
                '#fff',
                4.0,
            ),
        ]);

        $svg = (new SvgRenderer())->render($scene);

        $this->assertStringContainsString('transform="rotate(26.57 50 60)"', $svg);
        $this->assertStringContainsString('text-anchor="middle"', $svg);
        $this->assertStringContainsString('stroke="#fff"', $svg);
        $this->assertStringContainsString('paint-order="stroke"', $svg);
    }

    public function testUnknownNodeTypeThrows(): void
    {
        $scene = new Scene(100.0, 100.0, null, [
            new class implements NodeInterface {},
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot render scene node');

        (new SvgRenderer())->render($scene);
    }
}
