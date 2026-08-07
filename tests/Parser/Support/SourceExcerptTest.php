<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\SourceLine;
use Atelier\Diagram\Parser\Support\SourceExcerpt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/native_function_overrides.php';

#[CoversClass(SourceExcerpt::class)]
final class SourceExcerptTest extends TestCase
{
    protected function tearDown(): void
    {
        NativeFunctions::reset();
    }

    public function testBuildsExcerptFromLine(): void
    {
        $excerpt = SourceExcerpt::forLine(new Line(4, 'A --> B'));

        $this->assertSame(4, $excerpt->lineNumber);
        $this->assertSame('A --> B', $excerpt->content);
        $this->assertFalse($excerpt->truncated);
    }

    public function testBuildsExcerptFromRawSourceLine(): void
    {
        $excerpt = SourceExcerpt::forSourceLine(new SourceLine(6, '    todo [Todo]'));

        $this->assertSame(6, $excerpt->lineNumber);
        $this->assertSame('    todo [Todo]', $excerpt->content);
        $this->assertFalse($excerpt->truncated);
    }

    public function testTruncatesLongSourceContentToStablePreview(): void
    {
        $excerpt = SourceExcerpt::fromSource(9, str_repeat('a', 200));

        $this->assertSame(9, $excerpt->lineNumber);
        $this->assertTrue($excerpt->truncated);
        $this->assertSame(120, strlen($excerpt->content));
        $this->assertStringEndsWith('...', $excerpt->content);
    }

    public function testTruncatesLongMultibyteSourceContentWithoutBreakingUtf8(): void
    {
        $excerpt = SourceExcerpt::fromSource(11, str_repeat('é😀', 40));

        $this->assertSame(11, $excerpt->lineNumber);
        $this->assertTrue($excerpt->truncated);
        $this->assertLessThanOrEqual(120, strlen($excerpt->content));
        $this->assertStringEndsWith('...', $excerpt->content);
        $this->assertTrue(mb_check_encoding($excerpt->content, 'UTF-8'));
    }

    public function testTruncatesLongBrokenUtf8SourceContentWithoutBreakingUtf8(): void
    {
        $excerpt = SourceExcerpt::fromSource(12, str_repeat('é😀', 20)."\xC3");

        $this->assertSame(12, $excerpt->lineNumber);
        $this->assertTrue($excerpt->truncated);
        $this->assertLessThanOrEqual(120, strlen($excerpt->content));
        $this->assertStringEndsWith('...', $excerpt->content);
        $this->assertTrue(mb_check_encoding($excerpt->content, 'UTF-8'));
    }

    public function testTruncatesAsciiContentWithByteFallbackWhenMbStrcutMissing(): void
    {
        NativeFunctions::hide('mb_strcut');

        $excerpt = SourceExcerpt::fromSource(13, str_repeat('a', 200));

        $this->assertTrue($excerpt->truncated);
        $this->assertSame(120, strlen($excerpt->content));
        $this->assertStringEndsWith('...', $excerpt->content);
    }

    public function testTruncatesMultibyteContentWithByteFallbackWithoutBreakingUtf8(): void
    {
        NativeFunctions::hide('mb_strcut');

        $excerpt = SourceExcerpt::fromSource(14, str_repeat('é', 100));

        $this->assertTrue($excerpt->truncated);
        $this->assertLessThanOrEqual(120, strlen($excerpt->content));
        $this->assertStringEndsWith('...', $excerpt->content);
        $this->assertTrue(mb_check_encoding($excerpt->content, 'UTF-8'));
    }
}
