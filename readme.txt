=== Courier Export for WooCommerce ===
Contributors: saizul
Tags: woocommerce, courier, bangladesh, steadfast, pathao
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk-export WooCommerce orders to CSV files for Steadfast, Pathao, RedX or your own courier template, with Bangladeshi phone and COD handling.

== Description ==

Shipping with a Bangladeshi courier usually means copying every order into the courier panel by hand, or fixing a spreadsheet before each upload. Courier Export for WooCommerce turns the orders you select into a ready-to-upload CSV file.

**Bulk action on the Orders screen**

Select orders and choose "Export to Steadfast CSV", "Export to Pathao CSV", "Export to RedX CSV" or "Export to Generic template CSV". Works on the classic orders list and on the High-Performance Order Storage (HPOS) orders page.

**What it takes care of**

* Phone numbers normalised to 01XXXXXXXXX: +880, 880 and 00880 prefixes, missing leading zero, spaces and dashes, Bangla digits, two numbers in one field. Invalid numbers are exported with a warning or skipped.
* Cash-on-delivery amount: order total minus what was already paid (advance payments from an order meta key), 0 for orders paid online, rounding to whole taka.
* District names from WooCommerce states (BD-13 becomes Dhaka), old spellings recognised (Chittagong, Comilla, Jessore, Bogra...), plus your own district and area maps.
* Parcel weight from product weights in any unit, with a default per item and a minimum weight. RedX gets grams.
* Exported orders can be marked, noted and moved to another status, and skipped next time.
* Repeat orders for the same customer in one export (same phone number, optionally the same address) are flagged with a warning, or only the first one is exported, so a courier does not pick up two parcels for one double-clicked checkout.
* A summary of skipped orders and warnings after each export.

**Your own columns**

Every format is a list of "Header = template" lines that you can edit in the settings, for example:

`Name = {recipient.name|upper|truncate:60}`
`COD = {cod_amount|int}`

There are 19 template filters (upper, truncate, number, date, phone, join, grams and more). Developers can add couriers with the `woo_courier_export_formats` filter.

**About the column names**

The default columns follow the field names of each courier's public merchant API. The spreadsheet templates inside the courier panels are not publicly documented and change from time to time, so compare the columns with a sample file from your courier panel before the first upload and adjust the template if needed.

**For developers**

* WP-CLI: `wp courier-export export steadfast --status=processing --output=orders.csv`, `wp courier-export formats`, `wp courier-export phone <number>`.
* 16 filters and 4 actions, documented in the README on GitHub.
* A WordPress-free, unit-tested core.

Development happens on GitHub: https://github.com/saizul/woo-courier-export

== Installation ==

1. Upload the plugin zip in Plugins > Add New > Upload Plugin, or copy the `woo-courier-export` folder to `wp-content/plugins`.
2. Activate the plugin. WooCommerce must be active.
3. Open WooCommerce > Settings > Courier export and check the COD methods, weights and courier account values.
4. Select orders in WooCommerce > Orders and use the "Export to ... CSV" bulk action.

== Frequently Asked Questions ==

= Does it send orders to the courier automatically? =

No. It creates CSV files for the bulk-upload screen of your courier panel. It does not call courier APIs.

= My courier rejected the file. What should I do? =

Download the sample file from your courier panel and compare the header row. Edit the columns in WooCommerce > Settings > Courier export > Column templates so they match. If Excel shows broken Bangla text, keep the UTF-8 byte order mark enabled; if the courier rejects the BOM, turn it off.

= How is the COD amount calculated? =

For cash-on-delivery methods it is the order total minus the advance stored in the meta key you configure. Orders paid online export 0. Unpaid orders with other methods follow the setting "Unpaid orders with other methods".

= Pathao needs city and zone ids. =

Use the district and area maps in the settings ("Dhaka => 1"), or the `woo_courier_export_district` and `woo_courier_export_area` filters.

= A customer placed the same order twice. Will both be shipped? =

By default both orders are exported and flagged with a "same recipient" warning in the export summary. Set "Orders for the same recipient" to "Skipped" to export only the first one, or use `{recipient.duplicate_of}` in a column template to tell the courier which order it belongs to.

= Is it compatible with HPOS? =

Yes. Compatibility with custom order tables is declared and orders are only read and written through the WooCommerce CRUD API.

= Who can export? =

Users with the `manage_woocommerce` capability (filterable with `woo_courier_export_capability`).

== Screenshots ==

1. Export bulk actions on the Orders screen.
2. The Courier export settings tab.
3. The export summary with skipped orders and warnings.

== Changelog ==

= 0.1.0 =
* First release: Steadfast, Pathao, RedX and generic CSV formats, editable column templates, phone normalisation, COD calculation, district and area mapping, product weights, HPOS support, WP-CLI command, settings tab.

== Upgrade Notice ==

= 0.1.0 =
First release.
