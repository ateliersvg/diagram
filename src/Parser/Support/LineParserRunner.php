<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\InvalidArgumentException;
use Atelier\Diagram\Parser\Line;
use Atelier\Diagram\Parser\LineStream;

/**
 * Shared lifecycle for strict line-oriented Mermaid parsers.
 */
final readonly class LineParserRunner
{
    /**
     * @param non-empty-list<string> $headers
     */
    public function __construct(
        private array $headers,
        private string $expectedHeader,
        private ?string $missingHeader = null,
    ) {
    }

    /**
     * @param non-empty-list<string> $headers
     */
    public static function exact(array $headers, string $expectedHeader, ?string $missingHeader = null): self
    {
        return new self($headers, $expectedHeader, $missingHeader);
    }

    /**
     * @template TBuilder of object
     * @template TModel of object
     *
     * @param TBuilder                              $builder
     * @param callable(TBuilder, Line): void        $statement
     * @param callable(TBuilder): TModel            $build
     * @param (callable(TBuilder, Line): void)|null $finalize
     *
     * @return TModel
     */
    public function parse(string $source, object $builder, callable $statement, callable $build, ?callable $finalize = null, ?ParserInputLimits $limits = null): object
    {
        $headerLine = null;

        foreach (new LineStream($source, $limits) as $line) {
            if (null === $headerLine) {
                HeaderParser::exact($line, $this->headers, $this->expectedHeader);
                $headerLine = $line;
                continue;
            }

            try {
                $statement($builder, $line);
            } catch (InvalidArgumentException $exception) {
                throw ParseErrors::wrap($line, $exception);
            }
        }

        if (null === $headerLine) {
            throw ParseErrors::missingHeader($this->missingHeader ?? $this->expectedHeader);
        }

        return $this->finalize($headerLine, $builder, $build, $finalize);
    }

    /**
     * @template TBuilder of object
     * @template TModel of object
     *
     * @param TBuilder                              $builder
     * @param callable(TBuilder, Line): void        $statement
     * @param callable(TBuilder): TModel            $build
     * @param (callable(TBuilder, Line): void)|null $finalize
     *
     * @return TModel
     */
    public function parseWithHeader(string $source, object $builder, callable $statement, callable $build, HeaderMatch $header, ?callable $finalize = null, ?ParserInputLimits $limits = null): object
    {
        HeaderParser::exact($header->line, $this->headers, $this->expectedHeader);

        foreach (new LineStream($source, $limits, $header->nextOffset, $header->line->number + 1) as $line) {
            try {
                $statement($builder, $line);
            } catch (InvalidArgumentException $exception) {
                throw ParseErrors::wrap($line, $exception);
            }
        }

        return $this->finalize($header->line, $builder, $build, $finalize);
    }

    /**
     * @template TBuilder of object
     * @template TModel of object
     *
     * @param TBuilder                              $builder
     * @param callable(TBuilder): TModel            $build
     * @param (callable(TBuilder, Line): void)|null $finalize
     *
     * @return TModel
     */
    private function finalize(Line $header, object $builder, callable $build, ?callable $finalize): object
    {
        if (null !== $finalize) {
            try {
                $finalize($builder, $header);
            } catch (InvalidArgumentException $exception) {
                throw ParseErrors::wrap($header, $exception);
            }
        }

        try {
            return $build($builder);
        } catch (InvalidArgumentException $exception) {
            throw ParseErrors::wrap($header, $exception);
        }
    }
}
