# Security policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 0.1.x   | yes       |

## Reporting a vulnerability

Please do **not** open a public issue. Use GitHub's private vulnerability reporting
("Security" tab, "Report a vulnerability") on
<https://github.com/saizul/woo-courier-export>, or contact the maintainer through
<https://saizul.com>.

Include the affected version, a description and steps to reproduce. You can expect
an acknowledgement within 7 days. Please give us reasonable time to release a fix
before disclosing the issue.

## What the plugin does to stay safe

- Exports require the `manage_woocommerce` capability (filterable with
  `woo_courier_export_capability`), checked when listing bulk actions, running them
  and downloading the file.
- The download URL is protected by a nonce bound to a random, single-use job token
  that belongs to the user who started the export and expires after 10 minutes.
- Settings are saved through the WooCommerce settings API and sanitised by a strict
  schema; templates are compiled before they are stored.
- All admin output is escaped. CSV cells that could be read as spreadsheet formulas
  are neutralised.
- Templates cannot execute code or call functions; they can only read prepared
  order values.

Exported CSV files contain customer personal data (names, phone numbers,
addresses). Store and share them accordingly and delete them when they are no
longer needed.
