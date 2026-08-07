<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Sequence;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequenceBlockBranch::class)]
final class SequenceBlockBranchTest extends TestCase
{
    public function testConstructsWithLabelAndRange(): void
    {
        $branch = new SequenceBlockBranch('primary', 0, 2);

        $this->assertSame('primary', $branch->label);
        $this->assertSame(0, $branch->firstMessageIndex);
        $this->assertSame(2, $branch->lastMessageIndex);
    }

    public function testRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('block branch label must be non-empty');

        new SequenceBlockBranch('  ', 0, 1);
    }

    public function testRejectsInvalidRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('block branch message range is invalid');

        new SequenceBlockBranch('primary', 2, 1);
    }
}
