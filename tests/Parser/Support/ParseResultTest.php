<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Model\DiagramModel;
use Atelier\Diagram\Parser\Support\ParserDiagnostic;
use Atelier\Diagram\Parser\Support\ParseResult;
use Atelier\Diagram\Parser\Support\SourceSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParseResult::class)]
#[CoversClass(ParseException::class)]
#[CoversClass(ParserDiagnostic::class)]
#[CoversClass(SourceSpan::class)]
final class ParseResultTest extends TestCase
{
    public function testSuccessCarriesModelOnly(): void
    {
        $model = new class implements DiagramModel {
        };
        $result = ParseResult::success($model);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame($model, $result->getModel());
        $this->assertNull($result->getDiagnostic());
        $this->assertNull($result->getException());
    }

    public function testFailureCarriesDiagnosticAndException(): void
    {
        $exception = new ParseException(
            'Bad input',
            3,
            'bad',
            diagnostic: ParserDiagnostic::error('Bad input', SourceSpan::forLine(3, 'bad'), 'parser.syntax_error'),
        );
        $result = ParseResult::failure($exception);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertNull($result->getModel());
        $this->assertSame($exception, $result->getException());
        $this->assertSame('parser.syntax_error', $result->getDiagnostic()?->code);
    }
}
