<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests;

use PHPUnit\Framework\TestCase;
use WC_Order;
use WcStubs;
use WpStubs;

/**
 * Base class for tests of the WordPress layer (running on stubs).
 */
abstract class WpTestCase extends TestCase {

	protected function setUp(): void {
		WpStubs::reset();
		WcStubs::$orders     = array();
		WcStubs::$last_query = null;
		WcStubs::$states     = array( 'BD' => array( 'BD-13' => 'Dhaka', 'BD-10' => 'Chattogram' ) );
	}

	/**
	 * Create and register a fake order.
	 *
	 * @param array<string,mixed> $overrides Data overrides.
	 */
	protected function order( array $overrides = array() ): WC_Order {
		$order = new WC_Order(
			array_merge(
				array(
					'id'                   => 501,
					'number'               => 'WC-501',
					'status'               => 'processing',
					'date_created'         => '2026-11-05 10:00:00',
					'billing_first_name'   => 'Karim',
					'billing_last_name'    => 'Ahmed',
					'billing_phone'        => '+8801911234567',
					'billing_email'        => 'karim@example.com',
					'billing_address_1'    => 'Flat 3B, 22 Lake Road',
					'billing_address_2'    => '',
					'billing_city'         => 'Dhanmondi',
					'billing_state'        => 'BD-13',
					'billing_postcode'     => '1209',
					'billing_country'      => 'BD',
					'total'                => '2150.00',
					'shipping_total'       => '100.00',
					'payment_method'       => 'cod',
					'payment_method_title' => 'Cash on delivery',
					'currency'             => 'BDT',
					'customer_note'        => 'Office hours only',
					'items'                => array(
						array(
							'name'     => 'Saree',
							'quantity' => 1,
							'total'    => 1800,
							'tax'      => 0,
							'product'  => array( 'sku' => 'SR-1', 'weight' => '700' ),
						),
						array(
							'name'     => 'Gift wrap',
							'quantity' => 1,
							'total'    => 250,
							'product'  => array( 'sku' => 'GW', 'weight' => '', 'virtual' => true ),
						),
					),
				),
				$overrides
			)
		);
		WcStubs::$orders[ $order->get_id() ] = $order;
		return $order;
	}
}
