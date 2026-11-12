<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Location;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Location\Districts;
use Saizul\CourierExport\Location\LocationMapper;
use Saizul\CourierExport\Tests\Fixtures\Orders;
use Saizul\CourierExport\Tests\RecordingHooks;

final class LocationTest extends TestCase {

	public function test_all_64_districts(): void {
		$this->assertCount( 64, Districts::BY_CODE );
		$this->assertCount( 64, array_unique( Districts::BY_CODE ) );
		for ( $i = 1; $i <= 64; $i++ ) {
			$this->assertArrayHasKey( sprintf( 'BD-%02d', $i ), Districts::BY_CODE );
		}
	}

	public function test_aliases_point_to_real_districts(): void {
		foreach ( Districts::ALIASES as $alias => $name ) {
			$this->assertContains( $name, Districts::BY_CODE, $alias );
		}
	}

	/**
	 * @return array<string,array{0:string,1:string|null}>
	 */
	public function codeProvider(): array {
		return array(
			'iso'       => array( 'BD-13', 'Dhaka' ),
			'lower'     => array( 'bd-10', 'Chattogram' ),
			'no dash'   => array( 'BD60', 'Sylhet' ),
			'number'    => array( '1', 'Bandarban' ),
			'unknown'   => array( 'BD-99', null ),
			'garbage'   => array( 'Dhaka', null ),
		);
	}

	/**
	 * @dataProvider codeProvider
	 */
	public function test_name_for_code( string $code, ?string $expected ): void {
		$this->assertSame( $expected, Districts::name_for_code( $code ) );
	}

	/**
	 * @return array<string,array{0:string,1:string|null}>
	 */
	public function nameProvider(): array {
		return array(
			'exact'          => array( 'Dhaka', 'Dhaka' ),
			'case and space' => array( '  dhaka ', 'Dhaka' ),
			'old spelling'   => array( 'Chittagong', 'Chattogram' ),
			'comilla'        => array( 'Comilla', 'Cumilla' ),
			'jessore'        => array( 'Jessore', 'Jashore' ),
			'bogra'          => array( 'BOGRA', 'Bogura' ),
			'apostrophe'     => array( 'Coxs Bazar', "Cox's Bazar" ),
			'curly'          => array( 'Cox’s Bazar', "Cox's Bazar" ),
			'district word'  => array( 'Sylhet District', 'Sylhet' ),
			'sadar'          => array( 'Gazipur Sadar', 'Gazipur' ),
			'unknown'        => array( 'Mirpur', null ),
			'empty'          => array( '', null ),
		);
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function test_canonical_name( string $text, ?string $expected ): void {
		$this->assertSame( $expected, Districts::canonical_name( $text ) );
	}

	public function test_mapper_precedence(): void {
		$mapper = new LocationMapper();
		$this->assertSame( 'Dhaka', $mapper->district( Orders::cod() ) );
		$this->assertSame( 'Chattogram', $mapper->district( Orders::cod( array( 'state' => '', 'state_name' => 'Chittagong' ) ) ) );
		$this->assertSame( 'Sylhet', $mapper->district( Orders::cod( array( 'state' => '', 'state_name' => '', 'city' => 'sylhet' ) ) ) );
		$this->assertSame( 'Somewhere', $mapper->district( Orders::cod( array( 'state' => '', 'state_name' => 'Somewhere' ) ) ) );
		$this->assertSame( 'Mirpur', $mapper->district( Orders::cod( array( 'state' => '', 'state_name' => '' ) ) ) );
	}

	public function test_store_map_wins(): void {
		$mapper = new LocationMapper( array( 'bd-13' => 'Dhaka City' ), array( 'MIRPUR' => 'Mirpur-10' ) );
		$this->assertSame( 'Dhaka City', $mapper->district( Orders::cod() ) );
		$this->assertSame( 'Mirpur-10', $mapper->area( Orders::cod() ) );
		$this->assertSame( 'Uttara', $mapper->area( Orders::cod( array( 'city' => ' Uttara ' ) ) ) );
	}

	public function test_non_bd_orders_keep_state(): void {
		$mapper = new LocationMapper();
		$this->assertSame( 'West Bengal', $mapper->district( Orders::cod( array( 'country' => 'IN', 'state' => 'WB', 'state_name' => 'West Bengal' ) ) ) );
	}

	public function test_filters_receive_format(): void {
		$hooks = new RecordingHooks();
		$hooks->on(
			'woo_courier_export_area',
			static function ( $area, $order, $format ) {
				return $format . ':' . $area;
			}
		);
		$this->assertSame( 'pathao:Mirpur', ( new LocationMapper( array(), array(), $hooks ) )->area( Orders::cod(), 'pathao' ) );
	}
}
