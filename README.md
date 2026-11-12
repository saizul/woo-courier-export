# Courier Export for WooCommerce

[![CI](https://github.com/saizul/woo-courier-export/actions/workflows/ci.yml/badge.svg)](https://github.com/saizul/woo-courier-export/actions/workflows/ci.yml)
[![Release zip](https://github.com/saizul/woo-courier-export/actions/workflows/release-zip.yml/badge.svg)](https://github.com/saizul/woo-courier-export/actions/workflows/release-zip.yml)
![PHP](https://img.shields.io/badge/PHP-7.4%20to%208.3-777bb4)
![WooCommerce](https://img.shields.io/badge/WooCommerce-HPOS%20compatible-96588a)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Select orders, pick **Export to Steadfast / Pathao / RedX CSV** in the bulk actions,
and upload the file to your courier panel. The plugin normalises Bangladeshi phone
numbers to `01XXXXXXXXX`, works out the cash-on-delivery amount (total minus what
was already paid), converts WooCommerce states to district names, adds up the parcel
weight from your products and lets you define your own column template for any
other courier.

Plugin slug: `woo-courier-export` - display name: *Courier Export for WooCommerce*.

## Features

- **Bulk action on both orders screens**: the classic `edit.php?post_type=shop_order`
  list and the HPOS `admin.php?page=wc-orders` page. One action per enabled format.
- **Four formats out of the box**: Steadfast, Pathao, RedX and a readable generic
  file. Every format is a list of `Header = {template}` lines you can edit in the
  settings.
- **Phone normalisation**: `+880 1711-234567`, `8801711234567`, `008801711...`,
  `1711234567`, Bangla digits (`০১৭১১২৩৪৫৬৭`), several numbers in one field, labels
  like `Mobile:`. Invalid numbers (wrong length, landlines, operator `011`/`012`,
  `01711111111`-style placeholders) are flagged or skipped, your choice.
- **COD amount**: order total minus advance payments (from a meta key you choose) for
  COD methods you choose; 0 for orders paid online; a policy for unpaid bank-transfer
  orders; rounding to whole taka.
- **District and area mapping**: ISO 3166-2:BD state codes (`BD-13`) become district
  names (`Dhaka`), old spellings are recognised (`Chittagong` -> `Chattogram`,
  `Comilla` -> `Cumilla`, `Jessore` -> `Jashore`, `Bogra` -> `Bogura`...), plus your
  own `from => to` maps and filters to map to courier zone names or ids.
- **Weight from products**: product weights in any WooCommerce unit are converted to
  kg, items without a weight get a default, virtual items are ignored, a minimum
  parcel weight applies. RedX gets grams.
- **Safe template language**: `{recipient.name|upper|truncate:60}`,
  `{cod_amount|int}`, `{order.date|date:d/m/Y}`, 19 filters, no code execution, errors
  shown when you save the settings.
- **After export**: mark orders as exported, add an order note, move them to a status,
  skip already exported orders next time. A summary of skipped orders and warnings is
  shown after the download.
- **WP-CLI**: `wp courier-export export`, `formats` and `phone`.
- **Spreadsheet friendly CSV**: UTF-8 BOM for Excel and Bangla text, delimiter choice,
  CRLF line endings, formula-injection protection.
- **HPOS compatible**: compatibility is declared and orders are read and written only
  through `wc_get_order()`, `wc_get_orders()` and CRUD methods.
- **Extensible**: 15 filters and 4 actions, and a WordPress-free core you can reuse.
- **Translatable**: text domain `woo-courier-export`, `.pot` file included.

## About the courier column names

> **Read this before your first upload.**

The default column names follow the field names of each courier's **public merchant
API** (Steadfast `invoice`, `recipient_name`, `recipient_phone`, `cod_amount`...;
Pathao `merchant_order_id`, `recipient_city`, `amount_to_collect`...; RedX
`customer_name`, `cash_collection_amount`, `parcel_weight` in grams...). Those are the
same data the couriers' bulk-upload screens ask for.

The spreadsheet templates offered for download inside the Steadfast, Pathao and RedX
merchant panels are **not publicly documented**, could not be verified for this
release, and change from time to time. Treat the built-in formats as **configurable
templates**: download the current sample file from your courier panel, compare the
headers, and adjust the columns in *WooCommerce > Settings > Courier export > Column
templates* if they differ. Pathao's API uses numeric city/zone/area ids; the default
Pathao template writes names, so map them with the district/area maps or the
`woo_courier_export_district` / `woo_courier_export_area` filters if your upload
needs ids.

## Requirements

- WordPress 6.0 or newer
- WooCommerce 7.1 or newer (HPOS or legacy order storage)
- PHP 7.4 to 8.3

## Installation

**From a release (recommended):** download `woo-courier-export.zip` from the
[latest release](https://github.com/saizul/woo-courier-export/releases), then
*Plugins > Add New > Upload Plugin*. The zip contains only runtime files.

**From source:**

```bash
cd wp-content/plugins
git clone https://github.com/saizul/woo-courier-export.git
```

There are no runtime Composer dependencies; the plugin ships its own autoloader.
Activate it, then open *WooCommerce > Settings > Courier export*.

## Usage

1. Go to *WooCommerce > Orders*, tick the orders to ship.
2. Choose **Export to Steadfast CSV** (or Pathao, RedX, Generic) in *Bulk actions*
   and click *Apply*.
3. The CSV downloads. Reload the page to see a summary: orders skipped (for example
   an invalid phone number) and orders exported with warnings (no district found,
   zero weight, missing address).
4. Upload the file in your courier merchant panel.

Example Steadfast output:

```csv
invoice,recipient_name,recipient_phone,alternative_phone,recipient_address,cod_amount,note,item_description
1001,Rahim Uddin,01711234567,01811234567,"House 12, Road 5, Block C, Mirpur, Dhaka",1560,Call before delivery,"2 x Cotton Panjabi, 1 x Attar"
```

### WP-CLI

```bash
# Processing orders created in November to a Steadfast file
wp courier-export export steadfast --status=processing --after=2026-11-01 --output=steadfast.csv

# Specific orders to stdout, without marking them as exported
wp courier-export export pathao --ids=1201,1202,1203 --no-mark

# Check what would happen
wp courier-export export redx --status=processing,on-hold --skip-exported --dry-run

# Formats and their columns
wp courier-export formats --format=json

# Test phone normalisation
wp courier-export phone "+880 1711-234567" 1811234567 "০১৯১১২৩৪৫৬৭"
```

`export` options: `[<format>]` (defaults to the *Default format* setting), `--ids`,
`--status` (default `processing`), `--after`, `--before` (YYYY-MM-DD), `--limit`
(default 500), `--output`, `--skip-exported`, `--[no-]mark`, `--dry-run`. Orders are
loaded with `wc_get_orders()`.

## Configuration

*WooCommerce > Settings > Courier export*. Everything is stored in one option,
`woo_courier_export_settings`, and sanitised on save.

| Setting | Default | Notes |
| ------- | ------- | ----- |
| Formats in bulk actions | all | formats added by filters are always shown |
| Default format for WP-CLI | Steadfast | |
| CSV delimiter | comma | comma, semicolon, tab, pipe |
| UTF-8 byte order mark | on | needed for Bangla text in Excel |
| Cash on delivery methods | `cod` | any gateway ids |
| Advance payment meta key | empty | order meta holding an advance (e.g. delivery charge paid by bKash) |
| Unpaid orders with other methods | collect | collect the balance, or export 0 |
| Rounding | nearest taka | up, down, or 2 decimals |
| Weight per item without weight | 0.5 kg | |
| Minimum parcel weight | 0.5 kg | |
| Invalid phone numbers | export with warning | or skip the order |
| Mark exported orders | on | stores `_woo_courier_export_exported` (format) and `_exported_at` |
| Skip orders exported before | off | |
| Add an order note | on | |
| Change status after export | do not change | e.g. move to a custom "Shipped" status |
| Pathao store id / delivery type / item type | empty / 48 / 2 | `{store.*}` values |
| RedX pickup store id | empty | |
| District map / Area map | empty | `from => to` per line |
| Column templates | built-in | one textarea per format; empty means built-in |
| Delete settings on uninstall | off | order meta is kept |

### How the COD amount is calculated

| Order | COD amount |
| ----- | ---------- |
| COD method, nothing paid | total |
| COD method, advance in the meta key | total - advance |
| Other method, paid (`date_paid` set) | 0 |
| Other method, unpaid | total - advance, or 0 with "Export 0" |

The result is never negative and is rounded as configured. Override it per order
with the `woo_courier_export_cod_amount` filter.

### District resolution

1. Your district map, matched against the state code, state name and city.
2. The state code: `BD-13` -> `Dhaka` (all 64 ISO 3166-2:BD codes).
3. The state name or city recognised as a district, including old spellings and
   suffixes like "Sadar" or "District".
4. The state name as typed, then the city.

The area is the city field after your area map.

### Column templates

One column per line, `Header = template`. Text outside braces is copied, `{{` and
`}}` are literal braces, `#` starts a comment line.

```text
Invoice      = {order.number}
Name         = {recipient.name|single|truncate:100}
Phone        = {recipient.phone}
Address      = {recipient.address_with_area|single}
District     = {recipient.district}
COD          = {cod_amount|int}
Weight       = {weight_kg|number:1}
Instructions = Fragile. {order.note|single|default:"No note"}
```

**Values**

| Value | Meaning |
| ----- | ------- |
| `order.id`, `order.number`, `order.date`, `order.status` | order identifiers; `order.number` respects sequential-number plugins |
| `order.total`, `order.subtotal`, `order.shipping_total`, `order.paid` | amounts |
| `order.payment_method`, `order.payment_method_title`, `order.currency` | payment |
| `order.note`, `order.email` | customer note and email |
| `order.item_count`, `order.item_summary`, `order.items` | shippable quantity, "2 x Shirt, 1 x Cap", list of items (`order.items.0.sku`) |
| `recipient.name` | shipping name, falling back to billing |
| `recipient.phone` | normalised `01XXXXXXXXX` (as typed if invalid) |
| `recipient.phone_intl`, `recipient.phone_raw`, `recipient.phone_valid` | `+8801...`, original, 1/0 |
| `recipient.alt_phone` | billing phone when it differs from the shipping phone |
| `recipient.address`, `recipient.address_with_area` | lines + city; plus area and district |
| `recipient.address_1`, `recipient.address_2`, `recipient.city`, `recipient.postcode`, `recipient.country`, `recipient.state` | raw address parts |
| `recipient.district`, `recipient.area`, `recipient.area_or_district` | after mapping |
| `cod_amount`, `weight_kg`, `weight_g` | calculated |
| `store.pathao_store_id`, `store.pathao_delivery_type`, `store.pathao_item_type`, `store.redx_pickup_store_id` | settings |
| `meta.*` | `_woo_courier_export_exported` and anything you add with `woo_courier_export_order_data` |

**Filters** (chain with `|`, arguments with `:`; quote arguments that contain `:` or `|`)

| Filter | Example | Result |
| ------ | ------- | ------ |
| `upper`, `lower`, `title` | `{recipient.name\|upper}` | `RAHIM UDDIN` |
| `trim`, `single` | `{order.note\|single}` | collapse spaces / remove line breaks |
| `truncate:N[:END]` | `{order.note\|truncate:20:...}` | at most N characters |
| `number:D`, `int` | `{weight_kg\|number:2}`, `{cod_amount\|int}` | `1.25`, `1560` |
| `default:TEXT` | `{order.note\|default:-}` | fallback for empty values |
| `replace:FROM:TO` | `{order.number\|replace:WC-:}` | |
| `digits`, `phone`, `phone_intl` | `{recipient.phone_raw\|phone}` | `01711234567` |
| `date:FORMAT` | `{order.date\|date:d/m/Y}` | `01/11/2026` |
| `join:SEP[:KEY]`, `first` | `{order.items\|join:"; ":sku}` | `PJ-01; AT-02` |
| `grams` | `{weight_kg\|grams}` | `1250` |
| `prefix:TEXT`, `suffix:TEXT` | `{order.number\|prefix:INV-}` | only when not empty |

## Examples

### Add a courier (eCourier, Paperfly, Sundarban...)

```php
use Saizul\CourierExport\Format\FormatRegistry;
use Saizul\CourierExport\Format\TemplateFormat;

add_filter( 'woo_courier_export_formats', function ( FormatRegistry $formats ) {
	$formats->add(
		TemplateFormat::from_definition(
			'paperfly',
			'Paperfly',
			"Merchant Order Id = {order.number}\n" .
			"Customer Name = {recipient.name}\n" .
			"Customer Phone = {recipient.phone}\n" .
			"Customer Address = {recipient.address}\n" .
			"District = {recipient.district}\n" .
			"Thana = {recipient.area}\n" .
			"Collectable Amount = {cod_amount|int}\n" .
			"Weight (kg) = {weight_kg|number:1}"
		)
	);
	return $formats;
} );
```

The column names above are an illustration; use the headers from your courier.

### Map districts to Pathao city ids

```php
add_filter( 'woo_courier_export_district', function ( $district, $order, $format ) {
	if ( 'pathao' !== $format ) {
		return $district;
	}
	$ids = array( 'Dhaka' => '1', 'Chattogram' => '2' ); // Look the ids up in your Pathao account.
	return $ids[ $district ] ?? $district;
}, 10, 3 );
```

### Read an advance payment stored by another plugin

```php
add_filter( 'woo_courier_export_paid_amount', function ( $paid, WC_Order $order ) {
	return (float) $order->get_meta( '_partial_payment_amount' ) ?: $paid;
}, 10, 2 );
```

### Exclude orders and add a column

```php
add_filter( 'woo_courier_export_include_order', function ( $include, $order ) {
	return $include && 'pickup' !== ( $order->meta['delivery_method'] ?? '' );
}, 10, 2 );

add_filter( 'woo_courier_export_row', function ( array $row, $order, $format ) {
	$row[] = 'Handle with care';
	return $row;
}, 10, 3 );
```

(Add the header with a template override or a custom format so the columns line up.)

### Add a value for every template

```php
add_filter( 'woo_courier_export_context', function ( array $context, $order, $format ) {
	// Available as {extra.packed_by} in any column template.
	$context['extra'] = array( 'packed_by' => 'Warehouse A' );
	return $context;
}, 10, 3 );
```

## Hooks

Filters:

| Hook | Arguments | Purpose |
| ---- | --------- | ------- |
| `woo_courier_export_formats` | `FormatRegistry $registry` | add, replace, remove formats |
| `woo_courier_export_order_data` | `OrderData $data, WC_Order $order` | change the order snapshot |
| `woo_courier_export_paid_amount` | `float $paid, WC_Order $order` | amount already paid |
| `woo_courier_export_cod_amount` | `float $amount, OrderData $order` | final COD amount |
| `woo_courier_export_weight` | `float $kg, OrderData $order` | parcel weight |
| `woo_courier_export_phone` | `PhoneResult $result, OrderData $order` | phone normalisation result |
| `woo_courier_export_district` | `string $district, OrderData $order, string $format` | district value |
| `woo_courier_export_area` | `string $area, OrderData $order, string $format` | area value |
| `woo_courier_export_context` | `array $context, OrderData $order, string $format` | all template values |
| `woo_courier_export_include_order` | `bool $include, OrderData $order, string $format` | skip orders |
| `woo_courier_export_row` | `string[] $row, OrderData $order, string $format, array $context` | final row |
| `woo_courier_export_csv_options` | `array $options` | `delimiter`, `bom` |
| `woo_courier_export_filename` | `string $name, string $format` | download file name |
| `woo_courier_export_capability` | `string $capability` | default `manage_woocommerce` |
| `woo_courier_export_settings_fields` | `array $fields` | settings tab fields |

Actions: `woo_courier_export_loaded` (`Plugin $plugin`),
`woo_courier_export_before_export` (`int[] $ids, string $format`),
`woo_courier_export_after_export` (`ExportResult $result, string $format`),
`woo_courier_export_order_exported` (`WC_Order $order, string $format`).

## Security

- Exports need `manage_woocommerce` (filterable). Capability is checked when the
  bulk actions are listed, when the action runs and when the file is downloaded.
- The orders list tables verify their own bulk-action nonce before the handler runs; the download URL carries its own
  nonce bound to a random, single-use, user-bound job token that expires after 10
  minutes. Order ids never travel in the URL.
- Settings are saved through the WooCommerce settings API (nonce and capability
  checked by WooCommerce) and then sanitised by a strict schema. Templates are
  compiled before they are saved; invalid ones are rejected with a message.
- All admin output is escaped. CSV cells that start with `=`, `+`, `-`, `@`, tab or
  carriage return are prefixed with `'` so spreadsheets do not run them as formulas.
- The template language has no function calls or code execution and can only read
  the prepared values.

See [SECURITY.md](SECURITY.md) to report a vulnerability.

## Architecture

```text
src/        WordPress-free core (unit tested)
  Phone/      PhoneNormalizer, PhoneResult
  Money/      CodCalculator
  Weight/     WeightCalculator
  Location/   Districts (64 districts, aliases), LocationMapper
  Template/   TemplateEngine, ColumnParser, Column
  Format/     CourierFormat, TemplateFormat, BuiltInFormats, FormatRegistry
  Export/     ContextBuilder, Exporter, ExportResult, CsvWriter, ExporterFactory
  Settings/   SettingsSchema (defaults + sanitising)
includes/   WordPress and WooCommerce integration
  Plugin, Settings, ExportService, WpHooks
  Order/OrderMapper (WC_Order -> OrderData, CRUD only)
  Admin/BulkAction, Admin/DownloadController, Admin/SettingsTab
  Cli/ExportCommand
```

## Testing

```bash
composer install
composer test          # PHPUnit (no WordPress needed)
composer phpcs         # WordPress Coding Standards + PHPCompatibilityWP
composer lint          # php -l on every file
php bin/make-pot.php   # regenerate languages/woo-courier-export.pot
```

The core in `src/` is tested directly. The WordPress layer (`includes/`) is tested
against small stubs of the WordPress, WooCommerce and WP-CLI functions it uses
(`tests/Stubs/`), including a fake `WC_Order`. CI runs `php -l`, `phpcs` and
`phpunit` on PHP 7.4, 8.0, 8.1, 8.2 and 8.3 and checks that the `.pot` file is
current. Publishing a GitHub release runs `release-zip.yml`, which builds a clean
`woo-courier-export.zip` (files listed in `.distignore` are left out), checks the
version matches the tag and attaches the zip to the release.

## Limitations

- **Column names are not verified against the couriers' current upload templates**
  (see above). Check them once against a sample file from your panel.
- The plugin creates files only. It does not call courier APIs, book pickups or
  pull tracking numbers back into WooCommerce.
- District detection covers the 64 districts. Thana/upazila names are passed through
  from the city field; courier-specific zone names or ids need your area map or the
  `woo_courier_export_area` filter.
- Phone normalisation targets Bangladeshi mobile numbers. Other numbers are exported
  as typed (with a warning) or skipped.
- The bulk action exports the orders you select (no background processing). Very
  large selections are limited by PHP's memory and time limits; use WP-CLI with
  `--limit` for thousands of orders.
- The advance-payment amount must be stored in an order meta key (or provided by the
  `woo_courier_export_paid_amount` filter); the plugin does not detect partial
  payment plugins automatically.
- Not tested against a live WooCommerce install in CI: the WordPress layer is tested
  with stubs.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. Copyright (c) 2026 Saizul Amin ([saizul.com](https://saizul.com)).
See [LICENSE](LICENSE).
