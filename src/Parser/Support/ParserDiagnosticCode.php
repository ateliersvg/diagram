<?php

declare(strict_types=1);

namespace Atelier\Diagram\Parser\Support;

/**
 * Stable machine-readable parser diagnostic codes.
 */
enum ParserDiagnosticCode: string
{
    case BranchContext = 'parser.branch_context';
    case EmptyBlock = 'parser.empty_block';
    case EmptyBranch = 'parser.empty_branch';
    case EmptyInput = 'parser.empty_input';
    case EmptyLabel = 'parser.empty_label';
    case EmptyListItem = 'parser.empty_list_item';
    case EmptyValue = 'parser.empty_value';
    case ExpectedBlockKind = 'parser.expected_block_kind';
    case ExpectedHeader = 'parser.expected_header';
    case ExpectedOpenBlock = 'parser.expected_open_block';
    case IndentationExpected = 'parser.indentation_expected';
    case IndentationSkip = 'parser.indentation_skip';
    case IndentationStep = 'parser.indentation_step';
    case IndentationTabs = 'parser.indentation_tabs';
    case InvalidEnum = 'parser.invalid_enum';
    case LineTooLong = 'parser.line_too_long';
    case MissingHeader = 'parser.missing_header';
    case NestedBlock = 'parser.nested_block';
    case SemanticError = 'parser.semantic_error';
    case SourceTooLarge = 'parser.source_too_large';
    case SyntaxError = 'parser.syntax_error';
    case UnclosedBlock = 'parser.unclosed_block';
    case UnexpectedBlockEnd = 'parser.unexpected_block_end';
    case UnexpectedBranch = 'parser.unexpected_branch';
    case UnknownHeader = 'parser.unknown_header';
    case UnsupportedBranch = 'parser.unsupported_branch';
    case UnsupportedModel = 'parser.unsupported_model';
    case UnsupportedSyntax = 'parser.unsupported_syntax';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $code): string => $code->value, self::cases());
    }

    public static function isKnown(string $code): bool
    {
        return null !== self::tryFrom($code);
    }
}
