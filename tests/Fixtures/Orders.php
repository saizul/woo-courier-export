<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Fixtures;

use Saizul\CourierExport\Order\OrderData;

/**
 * Sample orders shared by tests.
 */
final class Orders {

	/**
	 * A typical COD order in Dhaka.
	 *
	 * @param array<string,mixed> $overrides Field overrides.
	 */
	public static function cod( array $overrides = array() ): OrderData {
		return OrderData::from_array(
			array_merge(
				array(
					'id'                   => 1001,
					'number'               => '1001',
					'date_created'         => '2026-11-01 14:30:00',
					'status'               => 'processing',
					'name'                 => 'Rahim Uddin',
					'phone'                => '+880 1711-234567',
					'alt_phone'            => '01811234567',
					'email'                => 'rahim@example.com',
					'address_1'            => 'House 12, Road 5',
					'address_2'            => 'Block C',
					'city'                 => 'Mirpur',
					'state'                => 'BD-13',
					'state_name'           => 'Dhaka',
					'postcode'             => '1216',
					'country'              => 'BD',
					'total'                => 1560.0,
					'shipping_total'       => 60.0,
					'paid_amount'          => 0.0,
					'payment_method'       => 'cod',
					'payment_method_title' => 'Cash on delivery',
					'customer_note'        => 'Call before delivery',
					'items'                => array(
						array(
							'name'      => 'Cotton Panjabi',
							'sku'       => 'PJ-01',
							'quantity'  => 2,
							'weight_kg' => 0.4,
							'total'     => 1200.0,
						),
						array(
							'name'      => 'Attar',
							'sku'       => 'AT-02',
							'quantity'  => 1,
							'weight_kg' => null,
							'total'     => 300.0,
						),
					),
				),
				$overrides
			)
		);
	}
}
