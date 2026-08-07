# Mermaid Parser Corpus

This fixture matrix is the cross-grammar contract for Mermaid parsing.

Run `composer parser` after parser changes. It runs the corpus verifier, parser
rule verifier, and parser PHPUnit suite.

Run `composer corpus` after adding, renaming, or deleting a fixture. It checks
metadata, required files, orphan directories, expected model classes, diagnostic
metadata, known diagnostic codes, and minimum accepted/rejected coverage for
every registered grammar.

Run `composer diagnostics:update` when a parser change intentionally changes a
human-readable rejected diagnostic. Review the `rejected-diagnostics.txt` diff
as parser UX, not as incidental test output.

Every grammar must have:

- at least one accepted sample, which proves parse/render canonical round trip;
- at least one rejected `parser.unsupported_syntax` sample;
- at least one rejected sample with a more specific diagnostic code, such as
  semantic, empty-label, indentation, enum, block, or source-span errors.

Accepted samples live in `accepted/<name>/`:

- `source.mmd`: user-style Mermaid input.
- `canonical.mmd`: exact canonical output expected after parse/render.
- `accepted.php`: maps `<name>` to the expected model class.

Rejected samples live in `rejected/<name>/`:

- `source.mmd`: input that must fail.
- `rejected.php`: maps `<name>` to the expected diagnostic code and optional
  source span/source excerpt. Diagnostic codes must exist in
  `Parser\Support\ParserDiagnosticCode`.
  - `startLine` / `endLine` pin the diagnostic span when range precision matters.
  - `source` pins `ParserDiagnostic::$source->content` for cases where the
    excerpt is part of the regression contract.
  - `parser.semantic_error` fixtures must declare `startLine`, `endLine`, and
    `source`, so builder-time failures cannot silently drift back to header-only
    diagnostics.
- `rejected-diagnostics.txt`: aggregate human-readable diagnostic snapshot for
  every rejected sample. It pins the formatter output used by CLI/editor
  surfaces: message, code, line number, source excerpt, and marker.

Add a fixture here when a parser feature changes canonicalization,
diagnostics, implicit declarations, block spans, or whitespace/comment
normalization. Parser-local unit tests should still cover branch-specific
behavior.
