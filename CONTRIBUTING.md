# Contributing

Thanks for helping improve Courier Export for WooCommerce. Bug reports, corrected
courier column lists, new district aliases and documentation fixes are all welcome.

## Reporting a bug

Open an issue with:

- WordPress, WooCommerce and PHP versions, and whether HPOS is enabled
- the format you exported and what you expected in the file
- for column problems: the header row of the sample file from your courier panel
  (no customer data)
- for phone or district problems: an example value, with personal data removed

Please never post real customer names, phone numbers or addresses.

## Courier templates

Couriers change their upload templates. If a built-in template no longer matches,
open an issue or pull request with the current header row and, if possible, a link
to where the courier publishes it. Update `src/Format/BuiltInFormats.php`, the tests
in `tests/Unit/Format/FormatsTest.php` and `tests/Unit/Export/ExporterTest.php`, and
the README.

## Development setup

```bash
git clone https://github.com/saizul/woo-courier-export.git
cd woo-courier-export
composer install
composer test     # PHPUnit
composer phpcs    # WordPress Coding Standards
composer lint     # php -l
```

PHP 7.4 or newer is required. The dependencies are resolved for PHP 7.4
(`config.platform`) so the same lock file works across the CI matrix.

## Guidelines

- Keep business logic in `src/` free of WordPress functions and cover it with tests.
  WordPress integration lives in `includes/` and is tested against the stubs in
  `tests/Stubs/`.
- Follow the WordPress Coding Standards (`composer phpcbf` fixes most issues).
- Escape all output, check capabilities and nonces for every request handler.
- Wrap user-facing strings in translation functions with the `woo-courier-export`
  text domain, add a `/* translators: */` comment for placeholders, and run
  `php bin/make-pot.php` to update the `.pot` file (CI checks it).
- New hooks start with `woo_courier_export_` and are documented in the README.
- Add an entry under "Unreleased" in `CHANGELOG.md`.

## Releasing

1. Update the version in `woo-courier-export.php` (header and constant),
   `includes/Plugin.php`, `readme.txt` (Stable tag and changelog) and `CHANGELOG.md`.
2. Tag `vX.Y.Z` and publish a GitHub release. The `Release zip` workflow checks the
   versions and attaches `woo-courier-export.zip`.

By contributing you agree that your contribution is licensed under the
GPL-2.0-or-later license of this project.
