<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Exception\ParseException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\Support\HeaderMatch;
use Atelier\Diagram\Parser\Support\HeaderParser;
use Atelier\Diagram\Parser\Support\LineParserRunner;
use Atelier\Diagram\Parser\Support\ParseErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineParserRunner::class)]
#[CoversClass(HeaderParser::class)]
#[CoversClass(ParseErrors::class)]
final class LineParserRunnerTest extends TestCase
{
    public function testParsesSignificantLinesAndBuildsModel(): void
    {
        $model = LineParserRunner::exact(['demo'], '"demo"')->parse(
            "%% comment\ndemo\n  first\n\n  second\n",
            new RunnerTestBuilder(),
            static function (RunnerTestBuilder $builder, Line $line): void {
                $builder->add($line->number, $line->content);
            },
            static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
        );

        $this->assertSame(['3:first', '5:second'], $model->values);
    }

    public function testParsesWithProvidedHeaderWithoutRescanning(): void
    {
        $model = LineParserRunner::exact(['demo'], '"demo"')->parseWithHeader(
            "demo\n  first\n\n  second\n",
            new RunnerTestBuilder(),
            static function (RunnerTestBuilder $builder, Line $line): void {
                $builder->add($line->number, $line->content);
            },
            static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
            new HeaderMatch(new Line(1, 'demo'), 5),
        );

        $this->assertSame(['2:first', '4:second'], $model->values);
    }

    public function testRejectsUnexpectedHeaderAtSourceLine(): void
    {
        try {
            LineParserRunner::exact(['demo'], '"demo"')->parse(
                "%% comment\nother\n",
                new RunnerTestBuilder(),
                static function (RunnerTestBuilder $_builder, Line $_line): void {},
                static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
            );
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Expected "demo" header at line 2: "other"', $exception->getMessage());
            $this->assertSame('parser.expected_header', $exception->getDiagnostic()->code);
            $this->assertSame(2, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(2, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('other', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testRejectsMissingHeader(): void
    {
        try {
            LineParserRunner::exact(['demo'], '"demo"')->parse(
                "%% comment\n\n",
                new RunnerTestBuilder(),
                static function (RunnerTestBuilder $_builder, Line $_line): void {},
                static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
            );
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Missing "demo" header at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.missing_header', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testRejectsMissingHeaderWithCustomExpectedText(): void
    {
        try {
            LineParserRunner::exact(['demo-v2', 'demo'], '"demo-v2" or "demo"', '"demo-v2"')->parse(
                '',
                new RunnerTestBuilder(),
                static function (RunnerTestBuilder $_builder, Line $_line): void {},
                static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
            );
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Missing "demo-v2" header at line 1: ""', $exception->getMessage());
            $this->assertSame('parser.missing_header', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testWrapsStatementBuilderErrorsAtStatementLine(): void
    {
        try {
            LineParserRunner::exact(['demo'], '"demo"')->parse(
                "demo\nvalid\ninvalid\n",
                new RunnerTestBuilder(),
                static function (RunnerTestBuilder $builder, Line $line): void {
                    if ('invalid' === $line->content) {
                        throw new InvalidArgumentException('Bad statement.');
                    }

                    $builder->add($line->number, $line->content);
                },
                static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
            );
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Bad statement at line 3: "invalid"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(3, $exception->getDiagnostic()->lineNumber());
        }
    }

    public function testWrapsBuildErrorsAtHeaderLine(): void
    {
        try {
            LineParserRunner::exact(['demo'], '"demo"')->parse(
                "demo\n",
                new RunnerTestBuilder(failBuild: true),
                static function (RunnerTestBuilder $builder, Line $line): void {
                    $builder->add($line->number, $line->content);
                },
                static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
            );
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Model incomplete at line 1: "demo"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('demo', $exception->getDiagnostic()->source?->content);
        }
    }

    public function testFinalizerRunsAfterStatementsAndBeforeBuild(): void
    {
        $model = LineParserRunner::exact(['demo'], '"demo"')->parse(
            "demo\nvalue\n",
            new RunnerTestBuilder(),
            static function (RunnerTestBuilder $builder, Line $line): void {
                $builder->add($line->number, $line->content);
            },
            static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
            static function (RunnerTestBuilder $builder, Line $header): void {
                $builder->add($header->number, 'finalize');
            },
        );

        $this->assertSame(['2:value', '1:finalize'], $model->values);
    }

    public function testFinalizerBuilderErrorsAreWrappedAtHeaderLine(): void
    {
        try {
            LineParserRunner::exact(['demo'], '"demo"')->parse(
                "demo\n",
                new RunnerTestBuilder(),
                static function (RunnerTestBuilder $_builder, Line $_line): void {},
                static fn (RunnerTestBuilder $builder): RunnerTestModel => $builder->build(),
                static function (RunnerTestBuilder $_builder, Line $_header): void {
                    throw new InvalidArgumentException('Finalize failed.');
                },
            );
            $this->fail('Expected a ParseException.');
        } catch (ParseException $exception) {
            $this->assertSame('Finalize failed at line 1: "demo"', $exception->getMessage());
            $this->assertSame('parser.semantic_error', $exception->getDiagnostic()->code);
            $this->assertSame(1, $exception->getDiagnostic()->span->startLine);
            $this->assertSame(1, $exception->getDiagnostic()->span->endLine);
            $this->assertSame('demo', $exception->getDiagnostic()->source?->content);
        }
    }
}

final class RunnerTestBuilder
{
    /**
     * @var list<string>
     */
    private array $values = [];

    public function __construct(
        private readonly bool $failBuild = false,
    ) {
    }

    public function add(int $line, string $value): void
    {
        $this->values[] = \sprintf('%d:%s', $line, $value);
    }

    public function build(): RunnerTestModel
    {
        if ($this->failBuild) {
            throw new InvalidArgumentException('Model incomplete.');
        }

        return new RunnerTestModel($this->values);
    }
}

final readonly class RunnerTestModel
{
    /**
     * @param list<string> $values
     */
    public function __construct(
        public array $values,
    ) {
    }
}
