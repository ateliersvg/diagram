<?php

declare(strict_types=1);

namespace Atelier\Diagram\Tests\Parser\Support;

use Atelier\Diagram\Parser\Support\ParserDiagnosticCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParserDiagnosticCode::class)]
final class ParserDiagnosticCodeTest extends TestCase
{
    public function testValuesAreUnique(): void
    {
        $values = ParserDiagnosticCode::values();

        $this->assertSame($values, array_values(array_unique($values)));
    }

    public function testKnowsStableParserCodes(): void
    {
        $expectedCodes = [
            'parser.branch_context',
            'parser.empty_block',
            'parser.empty_branch',
            'parser.empty_input',
            'parser.empty_label',
            'parser.empty_list_item',
            'parser.empty_value',
            'parser.expected_block_kind',
            'parser.expected_header',
            'parser.expected_open_block',
            'parser.indentation_expected',
            'parser.indentation_skip',
            'parser.indentation_step',
            'parser.indentation_tabs',
            'parser.invalid_enum',
            'parser.line_too_long',
            'parser.missing_header',
            'parser.nested_block',
            'parser.semantic_error',
            'parser.source_too_large',
            'parser.syntax_error',
            'parser.unclosed_block',
            'parser.unexpected_block_end',
            'parser.unexpected_branch',
            'parser.unknown_header',
            'parser.unsupported_branch',
            'parser.unsupported_model',
            'parser.unsupported_syntax',
        ];

        $this->assertSame($expectedCodes, ParserDiagnosticCode::values());
        foreach ($expectedCodes as $expectedCode) {
            $this->assertTrue(ParserDiagnosticCode::isKnown($expectedCode));
        }
        $this->assertFalse(ParserDiagnosticCode::isKnown('parser.typo'));
    }
}
