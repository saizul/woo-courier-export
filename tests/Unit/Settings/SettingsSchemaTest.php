<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Settings\SettingsSchema;

final class SettingsSchemaTest extends TestCase {

	public function test_empty_input_gives_defaults(): void {
		$this->assertSame( SettingsSchema::defaults(), SettingsSchema::sanitize( array() ) );
	}

	public function test_valid_values_are_kept(): void {
		$out = SettingsSchema::sanitize(
			array(
				'default_format'      => 'pathao',
				'cod_methods'         => array( 'cod', 'cash_on_pickup' ),
				'cod_rounding'        => 'ceil',
				'unpaid_non_cod'      => 'zero',
				'invalid_phone'       => 'skip',
				'delimiter'           => ';',
				'default_item_weight' => '0.75',
				'minimum_weight'      => '1',
				'skip_exported'       => 'yes',
				'bom'                 => '0',
				'status_after_export' => 'wc-completed',
				'pathao_store_id'     => '98765',
				'paid_meta_key'       => '_advance_payment',
			)
		);
		$this->assertSame( 'pathao', $out['default_format'] );
		$this->assertSame( array( 'cod', 'cash_on_pickup' ), $out['cod_methods'] );
		$this->assertSame( 'ceil', $out['cod_rounding'] );
		$this->assertSame( 'zero', $out['unpaid_non_cod'] );
		$this->assertSame( 'skip', $out['invalid_phone'] );
		$this->assertSame( ';', $out['delimiter'] );
		$this->assertSame( 0.75, $out['default_item_weight'] );
		$this->assertSame( 1.0, $out['minimum_weight'] );
		$this->assertTrue( $out['skip_exported'] );
		$this->assertFalse( $out['bom'] );
		$this->assertSame( 'completed', $out['status_after_export'] );
		$this->assertSame( '98765', $out['pathao_store_id'] );
		$this->assertSame( '_advance_payment', $out['paid_meta_key'] );
	}

	public function test_invalid_values_fall_back(): void {
		$out = SettingsSchema::sanitize(
			array(
				'default_format'      => '<script>',
				'cod_methods'         => array( 'bad method!', array() ),
				'cod_rounding'        => 'bankers',
				'delimiter'           => '"',
				'default_item_weight' => 'heavy',
				'minimum_weight'      => '-5',
				'status_after_export' => 'not a status',
				'pathao_store_id'     => '12; DROP',
				'paid_meta_key'       => 'has space',
				'unknown_key'         => 'x',
			)
		);
		$defaults = SettingsSchema::defaults();
		$this->assertSame( $defaults['default_format'], $out['default_format'] );
		$this->assertSame( array( 'cod' ), $out['cod_methods'] );
		$this->assertSame( 'round', $out['cod_rounding'] );
		$this->assertSame( ',', $out['delimiter'] );
		$this->assertSame( 0.5, $out['default_item_weight'] );
		$this->assertSame( 0.0, $out['minimum_weight'] );
		$this->assertSame( '', $out['status_after_export'] );
		$this->assertSame( '', $out['pathao_store_id'] );
		$this->assertSame( '', $out['paid_meta_key'] );
		$this->assertArrayNotHasKey( 'unknown_key', $out );
	}

	public function test_templates_are_validated(): void {
		$errors = array();
		$out    = SettingsSchema::sanitize(
			array(
				'template_steadfast' => "invoice = {order.number}\r\nphone = {recipient.phone}",
				'template_redx'      => 'bad = {order.number|explode}',
				'template_pathao'    => '   ',
			),
			$errors
		);
		$this->assertSame( "invoice = {order.number}\nphone = {recipient.phone}", $out['template_steadfast'] );
		$this->assertSame( '', $out['template_redx'] );
		$this->assertSame( '', $out['template_pathao'] );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'redx: Unknown filter "explode"', $errors[0] );
	}

	public function test_textareas_strip_control_characters(): void {
		$out = SettingsSchema::sanitize( array( 'district_map' => "a => b\x00\x07\r\nc => d" ) );
		$this->assertSame( "a => b\nc => d", $out['district_map'] );
	}

	public function test_to_storage_round_trip(): void {
		$stored = SettingsSchema::to_storage( SettingsSchema::defaults() );
		$this->assertSame( 'yes', $stored['bom'] );
		$this->assertSame( 'no', $stored['skip_exported'] );
		$this->assertSame( SettingsSchema::defaults(), SettingsSchema::sanitize( $stored ) );
	}

	public function test_template_error(): void {
		$this->assertNull( SettingsSchema::template_error( 'A = {order.id}' ) );
		$this->assertNotNull( SettingsSchema::template_error( 'no separator' ) );
	}

	public function test_enabled_formats(): void {
		$out = SettingsSchema::sanitize( array( 'enabled_formats' => array( 'redx', 'redx', 'Bad!' ) ) );
		$this->assertSame( array( 'redx' ), $out['enabled_formats'] );
	}
}
