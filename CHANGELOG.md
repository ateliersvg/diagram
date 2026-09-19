# Changelog

Public API and behaviour changes only. Versions follow Semantic Versioning.

## Unreleased

### Added

- `ThemeTextMeasurer`, metrics for theme fonts the default Helvetica table cannot express
- `TextMeasurerInterface` parameter on every layout engine constructor, passed by `LayoutRegistry`
- `ConnectionLabelPlacement` parameter on `ConnectionLabelArtist`

### Changed

- `atelier/layout` raised to `^0.8`, for its `WrapsText` trait

### Fixed

- Flowchart parser rejecting a node declared inside an edge, `A[Start] --> B[Stop]`
- Flowchart parser reading a whole edge line as one node label when it ended in `]`
- Quotes Mermaid uses to escape a label kept in the label text

## 0.7.0 - 2026-08-07

Initial release
