<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Parser\Support\LineBreakScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineBreakScanner::class)]
final class LineBreakScannerTest extends TestCase
{
    public function testConstructorIsPrivateStaticOnlyHelper(): void
    {
        $class = new \ReflectionClass(LineBreakScanner::class);
        $constructor = $class->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());

        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $this->assertInstanceOf(LineBreakScanner::class, $instance);
    }

    public function testReturnsNullPastEndOfSource(): void
    {
        $this->assertNull(LineBreakScanner::read('abc', 3, 3));
    }

    #[DataProvider('cases')]
    public function testReadsLinesAndOffsets(string $source, array $expected): void
    {
        $offset = 0;
        $length = strlen($source);
        $actual = [];

        while (null !== ($line = LineBreakScanner::read($source, $offset, $length))) {
            [$raw, $offset] = $line;
            $actual[] = $raw;
        }

        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function cases(): iterable
    {
        yield 'lf' => ["one\ntwo\nthree", ['one', 'two', 'three']];
        yield 'cr' => ["one\rtwo\rthree", ['one', 'two', 'three']];
        yield 'crlf' => ["one\r\ntwo\r\nthree", ['one', 'two', 'three']];
        yield 'vertical tab' => ["one\vtwo\vthree", ['one', 'two', 'three']];
        yield 'form feed' => ["one\ftwo\fthree", ['one', 'two', 'three']];
        yield 'bom' => ["\xEF\xBB\xBFone\ntwo", ['one', 'two']];
        yield 'utf-8 content' => ["café\nnaïve\n😀", ['café', 'naïve', '😀']];
        yield 'unicode line separator' => ["one\u{2028}two", ['one', 'two']];
        yield 'unicode paragraph separator' => ["one\u{2029}two", ['one', 'two']];
        yield 'nel' => ["one\u{0085}two", ['one', 'two']];
        yield 'bom only' => ["\xEF\xBB\xBF", ['']];
        yield 'non-break latin1 byte' => ["a\u{00A1}b", ["a\u{00A1}b"]];
        yield 'non-break three-byte char' => ["a\u{2022}b", ["a\u{2022}b"]];
        yield 'trailing lone c2 byte' => ["a\xC2", ["a\xC2"]];
        yield 'trailing lone e2 byte' => ["a\xE2", ["a\xE2"]];
    }
}
