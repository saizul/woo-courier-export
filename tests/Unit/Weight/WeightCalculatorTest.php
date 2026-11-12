<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Weight;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Order\OrderData;
use Saizul\CourierExport\Tests\Fixtures\Orders;
use Saizul\CourierExport\Weight\WeightCalculator;

final class WeightCalculatorTest extends TestCase {

	public function test_sums_weights_times_quantity(): void {
		// 2 x 0.4 kg + 1 x unknown (0 by default).
		$this->assertSame( 0.8, ( new WeightCalculator() )->calculate( Orders::cod() ) );
	}

	public function test_default_weight_for_items_without_weight(): void {
		$this->assertSame( 1.05, ( new WeightCalculator( 0.25 ) )->calculate( Orders::cod() ) );
	}

	public function test_minimum_weight(): void {
		$this->assertSame( 1.0, ( new WeightCalculator( 0.0, 1.0 ) )->calculate( Orders::cod() ) );
	}

	public function test_virtual_items_are_ignored(): void {
		$order = OrderData::from_array(
			array(
				'items' => array(
					array( 'name' => 'E-book', 'quantity' => 1, 'weight_kg' => 5, 'is_virtual' => true ),
					array( 'name' => 'Mug', 'quantity' => 3, 'weight_kg' => 0.35 ),
				),
			)
		);
		$this->assertSame( 1.05, ( new WeightCalculator() )->calculate( $order ) );
	}

	public function test_rounds_up_to_precision(): void {
		$this->assertSame( 0.4, ( new WeightCalculator( 0.0, 0.0, 1 ) )->finish( 0.31 ) );
		$this->assertSame( 0.3, ( new WeightCalculator( 0.0, 0.0, 1 ) )->finish( 0.1 + 0.2 ) );
		$this->assertSame( 2.0, ( new WeightCalculator( 0.0, 0.0, 0 ) )->finish( 1.2 ) );
	}

	public function test_empty_order_uses_minimum(): void {
		$this->assertSame( 0.5, ( new WeightCalculator( 0.0, 0.5 ) )->calculate( new OrderData() ) );
	}

	/**
	 * @return array<string,array{0:float,1:string,2:float}>
	 */
	public function unitProvider(): array {
		return array(
			'kg'  => array( 1.5, 'kg', 1.5 ),
			'g'   => array( 250.0, 'g', 0.25 ),
			'lbs' => array( 1.0, 'lbs', 0.45359237 ),
			'oz'  => array( 16.0, 'oz', 0.45359237 ),
			'KG'  => array( 2.0, ' KG ', 2.0 ),
		);
	}

	/**
	 * @dataProvider unitProvider
	 */
	public function test_unit_conversion( float $value, string $unit, float $expected ): void {
		$this->assertEqualsWithDelta( $expected, WeightCalculator::to_kg( $value, $unit ), 1e-9 );
	}

	public function test_unknown_unit_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		WeightCalculator::to_kg( 1.0, 'stone' );
	}
}
