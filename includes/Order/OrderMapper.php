<?php
/**
 * WC_Order to OrderData conversion.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP\Order;

use InvalidArgumentException;
use Saizul\CourierExport\Order\ItemData;
use Saizul\CourierExport\Order\OrderData;
use Saizul\CourierExport\Weight\WeightCalculator;
use WC_Order;

/**
 * Copies the fields the exporter needs from a WooCommerce order.
 *
 * Only CRUD getters are used, so it works the same with HPOS and with the
 * legacy posts storage.
 */
final class OrderMapper {

	public const EXPORTED_META    = '_woo_courier_export_exported';
	public const EXPORTED_AT_META = '_woo_courier_export_exported_at';

	/**
	 * Meta key holding an advance payment amount, empty for none.
	 *
	 * @var string
	 */
	private $paid_meta_key;

	/**
	 * Store weight unit.
	 *
	 * @var string
	 */
	private $weight_unit;

	/**
	 * Constructor.
	 *
	 * @param string $paid_meta_key Meta key with an advance payment amount.
	 * @param string $weight_unit   WooCommerce weight unit (kg, g, lbs, oz).
	 */
	public function __construct( string $paid_meta_key = '', string $weight_unit = 'kg' ) {
		$this->paid_meta_key = $paid_meta_key;
		$this->weight_unit   = isset( WeightCalculator::TO_KG[ strtolower( $weight_unit ) ] ) ? strtolower( $weight_unit ) : 'kg';
	}

	/**
	 * Convert an order.
	 *
	 * @param WC_Order $order Order.
	 */
	public function map( WC_Order $order ): OrderData {
		$data         = new OrderData();
		$data->id     = (int) $order->get_id();
		$data->number = (string) $order->get_order_number();
		$data->status = (string) $order->get_status();

		$created            = $order->get_date_created();
		$data->date_created = $created ? $created->date( 'Y-m-d H:i:s' ) : '';

		$use_shipping = $order->has_shipping_address();
		$prefix       = $use_shipping ? 'shipping' : 'billing';

		$first      = (string) $order->{"get_{$prefix}_first_name"}();
		$last       = (string) $order->{"get_{$prefix}_last_name"}();
		$data->name = trim( $first . ' ' . $last );
		if ( '' === $data->name ) {
			$data->name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}

		$shipping_phone  = method_exists( $order, 'get_shipping_phone' ) ? (string) $order->get_shipping_phone() : '';
		$billing_phone   = (string) $order->get_billing_phone();
		$data->phone     = '' !== trim( $shipping_phone ) ? $shipping_phone : $billing_phone;
		$data->alt_phone = '' !== trim( $shipping_phone ) && $shipping_phone !== $billing_phone ? $billing_phone : '';
		$data->email     = (string) $order->get_billing_email();

		$data->address_1 = (string) $order->{"get_{$prefix}_address_1"}();
		$data->address_2 = (string) $order->{"get_{$prefix}_address_2"}();
		$data->city      = (string) $order->{"get_{$prefix}_city"}();
		$data->state     = (string) $order->{"get_{$prefix}_state"}();
		$data->postcode  = (string) $order->{"get_{$prefix}_postcode"}();
		$data->country   = (string) $order->{"get_{$prefix}_country"}();
		if ( '' === $data->country ) {
			$data->country = 'BD';
		}
		$data->state_name = $this->state_name( $data->country, $data->state );

		$data->total                = (float) $order->get_total();
		$data->shipping_total       = (float) $order->get_shipping_total();
		$data->payment_method       = (string) $order->get_payment_method();
		$data->payment_method_title = (string) $order->get_payment_method_title();
		$data->currency             = (string) $order->get_currency();
		$data->customer_note        = (string) $order->get_customer_note();
		$data->is_paid              = null !== $order->get_date_paid();

		$paid = 0.0;
		if ( '' !== $this->paid_meta_key ) {
			$paid = (float) $order->get_meta( $this->paid_meta_key, true );
		} elseif ( $data->is_paid ) {
			$paid = $data->total;
		}
		$data->paid_amount = (float) apply_filters( 'woo_courier_export_paid_amount', $paid, $order );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$data->items[] = $this->map_item( $item );
		}

		$data->meta = array(
			self::EXPORTED_META    => (string) $order->get_meta( self::EXPORTED_META, true ),
			self::EXPORTED_AT_META => (string) $order->get_meta( self::EXPORTED_AT_META, true ),
		);

		return apply_filters( 'woo_courier_export_order_data', $data, $order );
	}

	/**
	 * Convert a line item.
	 *
	 * @param \WC_Order_Item $item Line item.
	 */
	private function map_item( $item ): ItemData {
		$data           = new ItemData();
		$data->name     = (string) $item->get_name();
		$data->quantity = (int) $item->get_quantity();
		$data->total    = (float) $item->get_total() + (float) $item->get_total_tax();

		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
		if ( $product ) {
			$data->sku        = (string) $product->get_sku();
			$data->is_virtual = (bool) $product->is_virtual();
			$weight           = $product->get_weight();
			if ( '' !== (string) $weight && is_numeric( $weight ) ) {
				try {
					$data->weight_kg = WeightCalculator::to_kg( (float) $weight, $this->weight_unit );
				} catch ( InvalidArgumentException $e ) {
					$data->weight_kg = null;
				}
			}
		}

		return $data;
	}

	/**
	 * State name from WooCommerce's country data.
	 *
	 * @param string $country Country code.
	 * @param string $state   State code.
	 */
	private function state_name( string $country, string $state ): string {
		if ( '' === $state || ! function_exists( 'WC' ) ) {
			return '';
		}
		$wc = WC();
		if ( ! $wc || ! isset( $wc->countries ) ) {
			return '';
		}
		$states = $wc->countries->get_states( $country );

		return is_array( $states ) && isset( $states[ $state ] ) ? html_entity_decode( (string) $states[ $state ], ENT_QUOTES, 'UTF-8' ) : '';
	}
}
