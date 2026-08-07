<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\ParseErrors;
use Atelier\Diagram\Parser\Support\SectionContentTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SectionContentTracker::class)]
#[CoversClass(ParseErrors::class)]
final class SectionContentTrackerTest extends TestCase
{
    public function testAllowsSectionWithItems(): void
    {
        $tracker = new SectionContentTracker('Timeline section "%s" must contain at least one event.');

        $tracker->begin(new Line(2, 'section Discovery'), 'Discovery');
        $tracker->touch();
        $tracker->assertClosed();

        $this->addToAssertionCount(1);
    }

    public function testRejectsPreviousEmptySectionWhenNewSectionStarts(): void
    {
        $tracker = new SectionContentTracker('Timeline section "%s" must contain at least one event.');
        $tracker->begin(new Line(2, 'section Discovery'), 'Discovery');

        try {
            $tracker->begin(new Line(3, 'section Build'), 'Build');
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Timeline section "Discovery" must contain at least one event at line 2: "section Discovery"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('section Discovery', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testRejectsOpenEmptySectionOnClose(): void
    {
        $tracker = new SectionContentTracker('Journey section "%s" must contain at least one task.');
        $tracker->begin(new Line(4, 'section Payment'), 'Payment');

        try {
            $tracker->assertClosed();
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Journey section "Payment" must contain at least one task at line 4: "section Payment"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(4, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(4, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('section Payment', $exception->getDiagnostic()->source?->content);
        }
    }
}
