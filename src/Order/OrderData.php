<?php
/**
 * Order snapshot used by the export pipeline.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Order;

/**
 * A plain copy of the order fields the exporter needs.
 *
 * Keeping the pipeline on this object instead of WC_Order makes every
 * formatter testable without WordPress and lets other plugins build orders
 * from any source.
 */
final class OrderData {

	/**
	 * Order id.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Order number shown to customers (may differ from the id).
	 *
	 * @var string
	 */
	public $number = '';

	/**
	 * Creation date in Y-m-d H:i:s (site time zone).
	 *
	 * @var string
	 */
	public $date_created = '';

	/**
	 * Order status without the wc- prefix.
	 *
	 * @var string
	 */
	public $status = '';

	/**
	 * Recipient full name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Primary phone as entered.
	 *
	 * @var string
	 */
	public $phone = '';

	/**
	 * Secondary phone as entered.
	 *
	 * @var string
	 */
	public $alt_phone = '';

	/**
	 * Email address.
	 *
	 * @var string
	 */
	public $email = '';

	/**
	 * Address line 1.
	 *
	 * @var string
	 */
	public $address_1 = '';

	/**
	 * Address line 2.
	 *
	 * @var string
	 */
	public $address_2 = '';

	/**
	 * City or thana/upazila as typed.
	 *
	 * @var string
	 */
	public $city = '';

	/**
	 * State code (for Bangladesh an ISO 3166-2:BD code such as BD-13).
	 *
	 * @var string
	 */
	public $state = '';

	/**
	 * State name resolved by WooCommerce, when available.
	 *
	 * @var string
	 */
	public $state_name = '';

	/**
	 * Postcode.
	 *
	 * @var string
	 */
	public $postcode = '';

	/**
	 * Country code.
	 *
	 * @var string
	 */
	public $country = 'BD';

	/**
	 * Order total including shipping and tax.
	 *
	 * @var float
	 */
	public $total = 0.0;

	/**
	 * Shipping total.
	 *
	 * @var float
	 */
	public $shipping_total = 0.0;

	/**
	 * Amount already paid (advance or full payment).
	 *
	 * @var float
	 */
	public $paid_amount = 0.0;

	/**
	 * Whether the order was marked paid by a gateway.
	 *
	 * @var bool
	 */
	public $is_paid = false;

	/**
	 * Payment method id, for example "cod" or "bkash".
	 *
	 * @var string
	 */
	public $payment_method = '';

	/**
	 * Payment method title.
	 *
	 * @var string
	 */
	public $payment_method_title = '';

	/**
	 * Currency code.
	 *
	 * @var string
	 */
	public $currency = 'BDT';

	/**
	 * Customer note left at checkout.
	 *
	 * @var string
	 */
	public $customer_note = '';

	/**
	 * Line items.
	 *
	 * @var ItemData[]
	 */
	public $items = array();

	/**
	 * Extra values (order meta, values added by filters).
	 *
	 * @var array<string,mixed>
	 */
	public $meta = array();

	/**
	 * Build from an associative array. Items may be ItemData or arrays.
	 *
	 * @param array<string,mixed> $data Values.
	 */
	public static function from_array( array $data ): self {
		$order = new self();
		foreach ( array( 'number', 'date_created', 'status', 'name', 'phone', 'alt_phone', 'email', 'address_1', 'address_2', 'city', 'state', 'state_name', 'postcode', 'country', 'payment_method', 'payment_method_title', 'currency', 'customer_note' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$order->{$key} = (string) $data[ $key ];
			}
		}
		foreach ( array( 'total', 'shipping_total', 'paid_amount' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$order->{$key} = (float) $data[ $key ];
			}
		}
		$order->id      = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$order->is_paid = ! empty( $data['is_paid'] );
		if ( '' === $order->number && $order->id > 0 ) {
			$order->number = (string) $order->id;
		}
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$order->meta = $data['meta'];
		}
		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$order->items[] = $item instanceof ItemData ? $item : ItemData::from_array( (array) $item );
			}
		}

		return $order;
	}

	/**
	 * Address lines and city joined into one line, skipping empty parts.
	 */
	public function full_address(): string {
		$parts = array();
		foreach ( array( $this->address_1, $this->address_2, $this->city ) as $part ) {
			$part = trim( $part );
			if ( '' !== $part && ! in_array( strtolower( $part ), array_map( 'strtolower', $parts ), true ) ) {
				$parts[] = $part;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Total quantity of shippable items.
	 */
	public function item_count(): int {
		$count = 0;
		foreach ( $this->items as $item ) {
			if ( ! $item->is_virtual ) {
				$count += max( 0, $item->quantity );
			}
		}

		return $count;
	}

	/**
	 * Short description such as "2 x T-shirt, 1 x Cap".
	 *
	 * @param int $max_length Maximum length; 0 for unlimited.
	 */
	public function item_summary( int $max_length = 0 ): string {
		$parts = array();
		foreach ( $this->items as $item ) {
			if ( $item->is_virtual ) {
				continue;
			}
			$parts[] = $item->quantity . ' x ' . $item->name;
		}
		$summary = implode( ', ', $parts );
		if ( $max_length > 0 && mb_strlen( $summary ) > $max_length ) {
			$summary = rtrim( mb_substr( $summary, 0, max( 1, $max_length - 3 ) ) ) . '...';
		}

		return $summary;
	}
}
