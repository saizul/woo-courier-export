<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\WP;

use Saizul\CourierExport\Tests\WpTestCase;
use Saizul\CourierExport\WP\Order\OrderMapper;

final class OrderMapperTest extends WpTestCase {

	public function test_maps_billing_fields_and_converts_grams(): void {
		$data = ( new OrderMapper( '', 'g' ) )->map( $this->order() );
		$this->assertSame( 501, $data->id );
		$this->assertSame( 'WC-501', $data->number );
		$this->assertSame( '2026-11-05 10:00:00', $data->date_created );
		$this->assertSame( 'Karim Ahmed', $data->name );
		$this->assertSame( '+8801911234567', $data->phone );
		$this->assertSame( '', $data->alt_phone );
		$this->assertSame( 'Dhanmondi', $data->city );
		$this->assertSame( 'BD-13', $data->state );
		$this->assertSame( 'Dhaka', $data->state_name );
		$this->assertSame( 2150.0, $data->total );
		$this->assertFalse( $data->is_paid );
		$this->assertSame( 0.0, $data->paid_amount );
		$this->assertCount( 2, $data->items );
		$this->assertEqualsWithDelta( 0.7, $data->items[0]->weight_kg, 1e-9 );
		$this->assertSame( 'SR-1', $data->items[0]->sku );
		$this->assertTrue( $data->items[1]->is_virtual );
		$this->assertNull( $data->items[1]->weight_kg );
	}

	public function test_shipping_address_and_phone_take_precedence(): void {
		$order = $this->order(
			array(
				'shipping_first_name' => 'Nusrat',
				'shipping_last_name'  => 'Jahan',
				'shipping_phone'      => '01711234567',
				'shipping_address_1'  => 'Agrabad C/A',
				'shipping_city'       => 'Agrabad',
				'shipping_state'      => 'BD-10',
				'shipping_country'    => 'BD',
			)
		);
		$data  = ( new OrderMapper() )->map( $order );
		$this->assertSame( 'Nusrat Jahan', $data->name );
		$this->assertSame( '01711234567', $data->phone );
		$this->assertSame( '+8801911234567', $data->alt_phone );
		$this->assertSame( 'Agrabad C/A', $data->address_1 );
		$this->assertSame( 'Chattogram', $data->state_name );
	}

	public function test_paid_meta_key_and_paid_orders(): void {
		$order = $this->order( array( 'meta' => array( '_advance' => '150' ) ) );
		$this->assertSame( 150.0, ( new OrderMapper( '_advance' ) )->map( $order )->paid_amount );

		$paid = $this->order( array( 'id' => 502, 'payment_method' => 'bkash', 'date_paid' => '2026-11-05 10:05:00' ) );
		$data = ( new OrderMapper() )->map( $paid );
		$this->assertTrue( $data->is_paid );
		$this->assertSame( 2150.0, $data->paid_amount );
	}

	public function test_filters_are_applied(): void {
		add_filter(
			'woo_courier_export_paid_amount',
			static function () {
				return 99.0;
			}
		);
		add_filter(
			'woo_courier_export_order_data',
			static function ( $data ) {
				$data->meta['custom'] = 'x';
				return $data;
			}
		);
		$data = ( new OrderMapper() )->map( $this->order() );
		$this->assertSame( 99.0, $data->paid_amount );
		$this->assertSame( 'x', $data->meta['custom'] );
	}

	public function test_exported_marker_is_read(): void {
		$order = $this->order( array( 'meta' => array( OrderMapper::EXPORTED_META => 'redx' ) ) );
		$this->assertSame( 'redx', ( new OrderMapper() )->map( $order )->meta[ OrderMapper::EXPORTED_META ] );
	}

	public function test_unknown_weight_unit_falls_back_to_kg(): void {
		$order = $this->order( array( 'items' => array( array( 'name' => 'Box', 'quantity' => 2, 'product' => array( 'weight' => '1.5' ) ) ) ) );
		$this->assertSame( 1.5, ( new OrderMapper( '', 'stone' ) )->map( $order )->items[0]->weight_kg );
	}

	public function test_missing_country_defaults_to_bd(): void {
		$this->assertSame( 'BD', ( new OrderMapper() )->map( $this->order( array( 'billing_country' => '' ) ) )->country );
	}
}
