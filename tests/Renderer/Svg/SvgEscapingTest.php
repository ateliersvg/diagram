<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Renderer\Svg;

use Atelier\Diagram\Diagram;
use Atelier\Diagram\Renderer\Svg\SvgRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Security regression test: free-text labels must never reach the SVG output
 * unescaped. An attacker-controlled label containing markup must be emitted as
 * XML entities inside <text>, not as live elements.
 */
#[CoversClass(SvgRenderer::class)]
final class SvgEscapingTest extends TestCase
{
    public function testFlowchartLabelMarkupIsEscapedInOutput(): void
    {
        $payload = '</text><script>alert(1)</script> & <"quote">';

        $svg = Diagram::fromMermaid("flowchart TD\n A[\"{$payload}\"]")->toSvg();

        // The dangerous characters are emitted as XML entities.
        $this->assertStringContainsString('&lt;/text&gt;&lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;"quote"&gt;', $svg);

        // The raw, executable script tag never appears.
        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringNotContainsString('</script>', $svg);

        // The injected closing </text> tag did not break out of the text node:
        // only the renderer's own <text>...</text> pair is present.
        $this->assertSame(1, substr_count($svg, '<text'));
        $this->assertSame(1, substr_count($svg, '</text>'));

        // Each escaped metacharacter is present in entity form.
        $this->assertStringContainsString('&lt;', $svg);
        $this->assertStringContainsString('&gt;', $svg);
        $this->assertStringContainsString('&amp;', $svg);
    }
}
