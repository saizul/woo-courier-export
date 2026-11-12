<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Money;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Money\CodCalculator;
use Saizul\CourierExport\Tests\Fixtures\Orders;

final class CodCalculatorTest extends TestCase {

	public function test_cod_order_collects_total(): void {
		$this->assertSame( 1560.0, ( new CodCalculator() )->calculate( Orders::cod() ) );
	}

	public function test_advance_is_deducted(): void {
		$order = Orders::cod( array( 'paid_amount' => 160.0 ) );
		$this->assertSame( 1400.0, ( new CodCalculator() )->calculate( $order ) );
	}

	public function test_paid_online_collects_nothing(): void {
		$order = Orders::cod( array( 'payment_method' => 'bkash', 'is_paid' => true, 'paid_amount' => 1560.0 ) );
		$this->assertSame( 0.0, ( new CodCalculator() )->calculate( $order ) );
	}

	public function test_paid_flag_without_amount_still_collects_nothing_for_non_cod(): void {
		$order = Orders::cod( array( 'payment_method' => 'sslcommerz', 'is_paid' => true ) );
		$this->assertSame( 0.0, ( new CodCalculator() )->calculate( $order ) );
	}

	public function test_unpaid_non_cod_policy(): void {
		$order = Orders::cod( array( 'payment_method' => 'bacs', 'is_paid' => false ) );
		$this->assertSame( 1560.0, ( new CodCalculator( array( 'cod' ), 'round', 'collect' ) )->calculate( $order ) );
		$this->assertSame( 0.0, ( new CodCalculator( array( 'cod' ), 'round', 'zero' ) )->calculate( $order ) );
	}

	public function test_cod_with_paid_flag_still_uses_balance(): void {
		// Some partial-payment plugins mark the order paid after the advance.
		$order = Orders::cod( array( 'is_paid' => true, 'paid_amount' => 100.0 ) );
		$this->assertSame( 1460.0, ( new CodCalculator() )->calculate( $order ) );
	}

	public function test_custom_cod_methods(): void {
		$calc = new CodCalculator( array( 'cod', 'cash_on_pickup' ) );
		$this->assertTrue( $calc->is_cod( 'cash_on_pickup' ) );
		$this->assertFalse( $calc->is_cod( 'bkash' ) );
		$this->assertSame( 500.0, $calc->amount( 500.0, 0.0, 'cash_on_pickup', false ) );
	}

	public function test_never_negative(): void {
		$this->assertSame( 0.0, ( new CodCalculator() )->amount( 100.0, 250.0, 'cod', false ) );
		$this->assertSame( 0.0, ( new CodCalculator() )->amount( -10.0, 0.0, 'cod', false ) );
	}

	/**
	 * @return array<string,array{0:string,1:float,2:float}>
	 */
	public function roundingProvider(): array {
		return array(
			'round down'     => array( 'round', 1499.49, 1499.0 ),
			'round half up'  => array( 'round', 1499.5, 1500.0 ),
			'ceil'           => array( 'ceil', 1499.01, 1500.0 ),
			'ceil noise'     => array( 'ceil', 1499.0000000001, 1499.0 ),
			'floor'          => array( 'floor', 1499.99, 1499.0 ),
			'none'           => array( 'none', 1499.456, 1499.46 ),
		);
	}

	/**
	 * @dataProvider roundingProvider
	 */
	public function test_rounding( string $mode, float $amount, float $expected ): void {
		$calc = new CodCalculator( array( 'cod' ), $mode );
		$this->assertSame( $expected, $calc->amount( $amount, 0.0, 'cod', false ) );
	}

	public function test_float_subtraction_is_clean(): void {
		$calc = new CodCalculator( array( 'cod' ), 'none' );
		$this->assertSame( 0.2, $calc->amount( 0.3, 0.1, 'cod', false ) );
	}

	public function test_invalid_rounding_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		new CodCalculator( array( 'cod' ), 'banker' );
	}

	public function test_invalid_policy_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		new CodCalculator( array( 'cod' ), 'round', 'maybe' );
	}
}
