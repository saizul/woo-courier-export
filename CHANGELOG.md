# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-12

### Added

- Bulk actions "Export to ... CSV" on the legacy and HPOS orders screens, with a
  user-bound, nonce-protected download and an export summary notice.
- Built-in Steadfast, Pathao, RedX and generic formats, each editable as
  `Header = {template}` lines in the settings.
- Template engine with dotted values and 19 filters; templates are validated on save.
- Bangladeshi phone normaliser (`01XXXXXXXXX`, `+880`, Bangla digits, placeholders).
- COD calculator (total minus paid, COD methods, unpaid policy, rounding).
- Weight calculator (unit conversion, default per item, minimum, virtual items).
- District mapping for all 64 districts (ISO 3166-2:BD codes and old spellings) and
  store-defined district/area maps.
- WooCommerce settings tab, HPOS compatibility declaration, `wc_get_orders()` based
  WP-CLI command `wp courier-export` (`export`, `formats`, `phone`).
- 15 filters and 4 actions, `.pot` file, `uninstall.php`.
- PHPUnit suite running without WordPress, WordPress Coding Standards config and
  GitHub workflows for CI and release zips.

[Unreleased]: https://github.com/saizul/woo-courier-export/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/saizul/woo-courier-export/releases/tag/v0.1.0
