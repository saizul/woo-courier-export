<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Order\OrderData;
use Saizul\CourierExport\Support\MapParser;
use Saizul\CourierExport\Support\NullHooks;

final class SupportTest extends TestCase {

	public function test_map_parser(): void {
		$text = "# comment\nBD-13 => Dhaka City\n\nSavar=Savar (Dhaka)\nTongi\tGazipur\nbroken line\n => nothing\nA => B => C";
		$this->assertSame(
			array(
				'BD-13' => 'Dhaka City',
				'Savar' => 'Savar (Dhaka)',
				'Tongi' => 'Gazipur',
				'A'     => 'B => C',
			),
			MapParser::parse( $text )
		);
	}

	public function test_map_dump(): void {
		$this->assertSame( "a => b\nc => d", MapParser::dump( array( 'a' => 'b', 'c' => 'd' ) ) );
	}

	public function test_null_hooks(): void {
		$hooks = new NullHooks();
		$this->assertSame( 5, $hooks->filter( 'x', 5, 'a' ) );
		$hooks->action( 'x' );
		$this->addToAssertionCount( 1 );
	}

	public function test_order_data_helpers(): void {
		$order = OrderData::from_array(
			array(
				'id'        => 9,
				'address_1' => 'Road 1',
				'address_2' => 'road 1',
				'city'      => 'Uttara',
				'items'     => array(
					array( 'name' => 'A very long product name', 'quantity' => 2 ),
					array( 'name' => 'Gift card', 'quantity' => 1, 'is_virtual' => true ),
				),
			)
		);
		$this->assertSame( '9', $order->number );
		$this->assertSame( 'Road 1, Uttara', $order->full_address() );
		$this->assertSame( 2, $order->item_count() );
		$this->assertSame( '2 x A very long product name', $order->item_summary() );
		$this->assertSame( '2 x A ve...', $order->item_summary( 11 ) );
	}
}
