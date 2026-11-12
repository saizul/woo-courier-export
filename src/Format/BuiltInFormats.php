<?php
/**
 * Default column templates for Bangladeshi couriers.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Format;

/**
 * Default templates for Steadfast, Pathao, RedX and a generic file.
 *
 * Column names mirror the field names of each courier's public merchant API
 * (the same data their bulk-upload screens ask for). Couriers change their
 * spreadsheet templates from time to time and the merchant-panel headers are
 * not publicly documented, so every template here can be replaced from the
 * settings screen or the `woo_courier_export_formats` filter. Download the
 * current sample file from your courier panel and compare before the first
 * upload.
 */
final class BuiltInFormats {

	public const STEADFAST = 'steadfast';
	public const PATHAO    = 'pathao';
	public const REDX      = 'redx';
	public const GENERIC   = 'generic';

	/**
	 * Steadfast (Packzy) consignment fields.
	 */
	public const STEADFAST_DEFINITION = <<<'TXT'
invoice           = {order.number}
recipient_name    = {recipient.name|single|truncate:100}
recipient_phone   = {recipient.phone}
alternative_phone = {recipient.alt_phone}
recipient_address = {recipient.address_with_area|single|truncate:250}
cod_amount        = {cod_amount|int}
note              = {order.note|single|truncate:200}
item_description  = {order.item_summary|truncate:200}
TXT;

	/**
	 * Pathao merchant order fields. City, zone and area are names here; map them
	 * to Pathao ids with the district/area maps or filters if your upload needs ids.
	 */
	public const PATHAO_DEFINITION = <<<'TXT'
store_id                  = {store.pathao_store_id}
merchant_order_id         = {order.number}
recipient_name            = {recipient.name|single|truncate:100}
recipient_phone           = {recipient.phone}
recipient_secondary_phone = {recipient.alt_phone}
recipient_address         = {recipient.address|single|truncate:220}
recipient_city            = {recipient.district}
recipient_zone            = {recipient.area}
recipient_area            =
delivery_type             = {store.pathao_delivery_type|default:48}
item_type                 = {store.pathao_item_type|default:2}
special_instruction       = {order.note|single|truncate:200}
item_quantity             = {order.item_count}
item_weight               = {weight_kg|number:1}
amount_to_collect         = {cod_amount|int}
item_description          = {order.item_summary|truncate:200}
TXT;

	/**
	 * RedX parcel fields. RedX expects the parcel weight in grams.
	 */
	public const REDX_DEFINITION = <<<'TXT'
customer_name          = {recipient.name|single|truncate:100}
customer_phone         = {recipient.phone}
delivery_area          = {recipient.area_or_district}
delivery_area_id       =
customer_address       = {recipient.address|single|truncate:250}
merchant_invoice_id    = {order.number}
cash_collection_amount = {cod_amount|int}
parcel_weight          = {weight_g}
value                  = {order.subtotal|int}
instruction            = {order.note|single|truncate:200}
pickup_store_id        = {store.redx_pickup_store_id}
TXT;

	/**
	 * A readable all-purpose file, also the starting point for custom templates.
	 */
	public const GENERIC_DEFINITION = <<<'TXT'
Order         = {order.number}
Date          = {order.date|date:Y-m-d}
Name          = {recipient.name|single}
Phone         = {recipient.phone}
Alt phone     = {recipient.alt_phone}
Address       = {recipient.address|single}
Area          = {recipient.area}
District      = {recipient.district}
Postcode      = {recipient.postcode}
COD amount    = {cod_amount|int}
Order total   = {order.total|number:2}
Payment       = {order.payment_method_title}
Weight (kg)   = {weight_kg|number:2}
Items         = {order.item_summary}
Quantity      = {order.item_count}
Note          = {order.note|single}
TXT;

	/**
	 * Id => [label, definition] for the built-in formats.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function definitions(): array {
		return array(
			self::STEADFAST => array( 'Steadfast', self::STEADFAST_DEFINITION ),
			self::PATHAO    => array( 'Pathao', self::PATHAO_DEFINITION ),
			self::REDX      => array( 'RedX', self::REDX_DEFINITION ),
			self::GENERIC   => array( 'Generic template', self::GENERIC_DEFINITION ),
		);
	}

	/**
	 * Build the built-in formats, optionally overriding definitions.
	 *
	 * @param array<string,string> $overrides Format id => definition text (empty values ignored).
	 * @param array<string,string> $labels    Format id => translated label.
	 * @return array<string,TemplateFormat>
	 */
	public static function create( array $overrides = array(), array $labels = array() ): array {
		$formats = array();
		foreach ( self::definitions() as $id => $definition ) {
			$text           = isset( $overrides[ $id ] ) && '' !== trim( $overrides[ $id ] ) ? $overrides[ $id ] : $definition[1];
			$formats[ $id ] = TemplateFormat::from_definition( $id, $labels[ $id ] ?? $definition[0], $text );
		}

		return $formats;
	}
}
