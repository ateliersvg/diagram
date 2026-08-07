<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

use Atelier\Diagram\Exception\ParseException;

/**
 * Non-throwing parse outcome for CLI/editor integrations.
 */
final readonly class ParseResult
{
    private function __construct(
        private ?object $model,
        private ?ParserDiagnostic $diagnostic,
        private ?ParseException $exception,
    ) {
    }

    public static function success(object $model): self
    {
        return new self($model, null, null);
    }

    public static function failure(ParseException $exception): self
    {
        return new self(null, $exception->getDiagnostic(), $exception);
    }

    public function isSuccess(): bool
    {
        return null !== $this->model;
    }

    public function isFailure(): bool
    {
        return null !== $this->diagnostic;
    }

    public function getModel(): ?object
    {
        return $this->model;
    }

    public function getDiagnostic(): ?ParserDiagnostic
    {
        return $this->diagnostic;
    }

    public function getException(): ?ParseException
    {
        return $this->exception;
    }
}
