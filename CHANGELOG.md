# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-06-20

### Added
- Reworked the output table and Excel export to display each attribute in its own column for clarity.
- Added a "Product Name" column to the output table and Excel export.
- Implemented hierarchical SKU generation to correctly use explicit variation SKUs (e.g., for a specific size) as a base for combinations.
- Integrated a fix to correctly format SKUs for double-sided laminate products by replacing hyphens with periods between specific attributes.

### Fixed
- Completely refactored the plugin to centralize logic into a single class, resolving numerous bugs related to page loading, PHP deprecation notices, and corrupt Excel exports.
- Corrected the core data-gathering logic to ensure all variation attributes are considered for matching, not just those with suffixes.

## [1.0.2]

### Fixed
- Initial bug fixes and stability improvements. 