<?php
/**
 * Minimal WooCommerce class and function stubs for unit tests.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

// phpcs:ignoreFile

class WC_DateTime extends DateTime {
	public function date( $format ) {
		return $this->format( $format );
	}
}

class WC_Product {
	/** @var array<string,mixed> */
	private $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function get_sku() {
		return $this->data['sku'] ?? '';
	}

	public function get_weight() {
		return $this->data['weight'] ?? '';
	}

	public function is_virtual() {
		return ! empty( $this->data['virtual'] );
	}
}

class WC_Order_Item {
	/** @var array<string,mixed> */
	private $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function get_name() {
		return $this->data['name'] ?? '';
	}

	public function get_quantity() {
		return $this->data['quantity'] ?? 1;
	}

	public function get_total() {
		return $this->data['total'] ?? 0;
	}

	public function get_total_tax() {
		return $this->data['tax'] ?? 0;
	}

	public function get_product() {
		return isset( $this->data['product'] ) ? new WC_Product( $this->data['product'] ) : false;
	}
}

/**
 * Fake order with CRUD-style getters. Unknown get_* calls read $data.
 */
class WC_Order {
	/** @var array<string,mixed> */
	public $data;
	/** @var array<string,mixed> */
	public $meta = array();
	/** @var string[] */
	public $notes = array();
	/** @var int */
	public $saves = 0;

	public function __construct( array $data ) {
		$this->data = $data;
		$this->meta = $data['meta'] ?? array();
	}

	public function __call( $name, $args ) {
		if ( 0 === strpos( $name, 'get_' ) ) {
			return $this->data[ substr( $name, 4 ) ] ?? '';
		}
		throw new BadMethodCallException( $name );
	}

	public function get_id() {
		return $this->data['id'];
	}

	public function get_type() {
		return $this->data['type'] ?? 'shop_order';
	}

	public function get_order_number() {
		return (string) ( $this->data['number'] ?? $this->data['id'] );
	}

	public function get_status() {
		return $this->data['status'] ?? 'processing';
	}

	public function get_date_created() {
		return isset( $this->data['date_created'] ) ? new WC_DateTime( $this->data['date_created'] ) : null;
	}

	public function get_date_paid() {
		return isset( $this->data['date_paid'] ) ? new WC_DateTime( $this->data['date_paid'] ) : null;
	}

	public function get_shipping_phone() {
		return $this->data['shipping_phone'] ?? '';
	}

	public function has_shipping_address() {
		return ! empty( $this->data['shipping_address_1'] ) || ! empty( $this->data['shipping_city'] );
	}

	public function get_items( $type = 'line_item' ) {
		return array_map(
			static function ( $item ) {
				return new WC_Order_Item( $item );
			},
			$this->data['items'] ?? array()
		);
	}

	public function get_meta( $key, $single = true ) {
		return $this->meta[ $key ] ?? '';
	}

	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	public function add_order_note( $note ) {
		$this->notes[] = $note;
		return 1;
	}

	public function update_status( $status, $note = '' ) {
		$this->data['status'] = $status;
		$this->notes[]        = $note;
		++$this->saves;
		return true;
	}

	public function save() {
		++$this->saves;
		return $this->get_id();
	}
}

final class WcStubs {
	/** @var array<int,WC_Order> */
	public static $orders = array();
	/** @var array<string,mixed>|null */
	public static $last_query = null;
	/** @var array<string,array<string,string>> */
	public static $states = array();
}

class WC_Countries_Stub {
	public function get_states( $country ) {
		return WcStubs::$states[ $country ] ?? false;
	}
}

class WC_Gateway_Stub {
	/** @var string */
	private $title;

	public function __construct( string $title ) {
		$this->title = $title;
	}

	public function get_method_title() {
		return $this->title;
	}
}

class WC_Payment_Gateways_Stub {
	public function payment_gateways() {
		return array(
			'cod'   => new WC_Gateway_Stub( 'Cash on delivery' ),
			'bkash' => new WC_Gateway_Stub( '<b>bKash</b>' ),
		);
	}
}

class WooCommerce_Stub {
	/** @var WC_Countries_Stub */
	public $countries;

	public function __construct() {
		$this->countries = new WC_Countries_Stub();
	}

	public function payment_gateways() {
		return new WC_Payment_Gateways_Stub();
	}
}

function wc_get_order_statuses() {
	return array(
		'wc-pending'    => 'Pending payment',
		'wc-processing' => 'Processing',
		'wc-completed'  => 'Completed',
	);
}

function WC() {
	static $wc = null;
	if ( null === $wc ) {
		$wc = new WooCommerce_Stub();
	}
	return $wc;
}

function wc_get_order( $id ) {
	return WcStubs::$orders[ (int) $id ] ?? false;
}

function wc_get_orders( $args ) {
	WcStubs::$last_query = $args;
	$statuses            = (array) ( $args['status'] ?? array() );
	$out                 = array();
	foreach ( WcStubs::$orders as $order ) {
		if ( array() === $statuses || in_array( $order->get_status(), $statuses, true ) ) {
			$out[] = $order;
		}
	}
	return array_slice( $out, 0, (int) ( $args['limit'] ?? 10 ) );
}
