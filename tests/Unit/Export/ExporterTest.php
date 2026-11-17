<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Export\CsvWriter;
use Saizul\CourierExport\Export\Exporter;
use Saizul\CourierExport\Export\ExporterFactory;
use Saizul\CourierExport\Export\ExportOptions;
use Saizul\CourierExport\Format\BuiltInFormats;
use Saizul\CourierExport\Format\TemplateFormat;
use Saizul\CourierExport\Phone\PhoneResult;
use Saizul\CourierExport\Tests\Fixtures\Orders;
use Saizul\CourierExport\Tests\RecordingHooks;

final class ExporterTest extends TestCase {

	/**
	 * Settings used by most tests.
	 *
	 * @return array<string,mixed>
	 */
	private function settings( array $overrides = array() ): array {
		return array_merge(
			array(
				'default_item_weight' => 0.2,
				'minimum_weight'      => 0.5,
				'pathao_store_id'     => '12345',
				'redx_pickup_store_id' => '777',
			),
			$overrides
		);
	}

	/**
	 * Export one format and return rows keyed by header.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function export( string $format, array $orders, array $settings = array(), ?RecordingHooks $hooks = null ): array {
		$settings = $this->settings( $settings );
		$exporter = ExporterFactory::create( $settings, $hooks );
		$result   = $exporter->export( $orders, BuiltInFormats::create()[ $format ], ExporterFactory::options( $settings ) );
		return array_map(
			static function ( array $row ) use ( $result ): array {
				return array_combine( $result->headers, $row );
			},
			$result->rows
		);
	}

	public function test_steadfast_row(): void {
		$rows = $this->export( 'steadfast', array( Orders::cod() ) );
		$this->assertSame(
			array(
				'invoice'           => '1001',
				'recipient_name'    => 'Rahim Uddin',
				'recipient_phone'   => '01711234567',
				'alternative_phone' => '01811234567',
				'recipient_address' => 'House 12, Road 5, Block C, Mirpur, Dhaka',
				'cod_amount'        => '1560',
				'note'              => 'Call before delivery',
				'item_description'  => '2 x Cotton Panjabi, 1 x Attar',
			),
			$rows[0]
		);
	}

	public function test_pathao_row(): void {
		$rows = $this->export( 'pathao', array( Orders::cod( array( 'paid_amount' => 60 ) ) ) );
		$row  = $rows[0];
		$this->assertSame( '12345', $row['store_id'] );
		$this->assertSame( '1001', $row['merchant_order_id'] );
		$this->assertSame( 'Dhaka', $row['recipient_city'] );
		$this->assertSame( 'Mirpur', $row['recipient_zone'] );
		$this->assertSame( '48', $row['delivery_type'] );
		$this->assertSame( '2', $row['item_type'] );
		$this->assertSame( '3', $row['item_quantity'] );
		$this->assertSame( '1.0', $row['item_weight'] );
		$this->assertSame( '1500', $row['amount_to_collect'] );
	}

	public function test_redx_row_uses_grams_and_value(): void {
		$rows = $this->export( 'redx', array( Orders::cod() ) );
		$row  = $rows[0];
		$this->assertSame( '1000', $row['parcel_weight'] );
		$this->assertSame( '1500', $row['value'] );
		$this->assertSame( 'Mirpur', $row['delivery_area'] );
		$this->assertSame( '777', $row['pickup_store_id'] );
		$this->assertSame( '1560', $row['cash_collection_amount'] );
	}

	public function test_redx_area_falls_back_to_district(): void {
		$rows = $this->export( 'redx', array( Orders::cod( array( 'city' => '' ) ) ) );
		$this->assertSame( 'Dhaka', $rows[0]['delivery_area'] );
	}

	public function test_generic_row(): void {
		$rows = $this->export( 'generic', array( Orders::cod() ) );
		$this->assertSame( '2026-11-01', $rows[0]['Date'] );
		$this->assertSame( '1560.00', $rows[0]['Order total'] );
		$this->assertSame( '1.00', $rows[0]['Weight (kg)'] );
		$this->assertSame( 'Cash on delivery', $rows[0]['Payment'] );
	}

	public function test_prepaid_order_has_zero_cod(): void {
		$rows = $this->export( 'steadfast', array( Orders::cod( array( 'payment_method' => 'bkash', 'is_paid' => true ) ) ) );
		$this->assertSame( '0', $rows[0]['cod_amount'] );
	}

	public function test_invalid_phone_included_with_warning(): void {
		$exporter = ExporterFactory::create( $this->settings() );
		$result   = $exporter->export( array( Orders::cod( array( 'phone' => '12345' ) ) ), BuiltInFormats::create()['steadfast'] );
		$this->assertSame( 1, $result->count() );
		$this->assertSame( '12345', $result->rows[0][2] );
		$this->assertSame( array( 1001 => array( Exporter::WARN_INVALID_PHONE ) ), $result->warnings );
	}

	public function test_invalid_phone_skipped_when_configured(): void {
		$settings = $this->settings( array( 'invalid_phone' => 'skip' ) );
		$exporter = ExporterFactory::create( $settings );
		$orders   = array( Orders::cod( array( 'phone' => '12345' ) ), Orders::cod( array( 'id' => 1002, 'number' => '1002' ) ) );
		$result   = $exporter->export( $orders, BuiltInFormats::create()['steadfast'], ExporterFactory::options( $settings ) );
		$this->assertSame( array( 1002 ), $result->exported_ids );
		$this->assertSame( array( 1001 => Exporter::SKIP_INVALID_PHONE ), $result->skipped );
	}

	public function test_skip_exported(): void {
		$settings = $this->settings( array( 'skip_exported' => true ) );
		$orders   = array( Orders::cod( array( 'meta' => array( '_woo_courier_export_exported' => 'steadfast' ) ) ) );
		$result   = ExporterFactory::create( $settings )->export( $orders, BuiltInFormats::create()['steadfast'], ExporterFactory::options( $settings ) );
		$this->assertSame( 0, $result->count() );
		$this->assertSame( array( 1001 => Exporter::SKIP_EXPORTED ), $result->skipped );
	}

	public function test_zero_weight_warning(): void {
		$order  = Orders::cod(
			array(
				'id'        => 2,
				'phone'     => '01911234567',
				'name'      => '',
				'address_1' => '',
				'address_2' => '',
				'city'      => '',
				'state'     => '',
				'state_name' => '',
			)
		);
		$result = ExporterFactory::create( array( 'minimum_weight' => 0, 'default_item_weight' => 0 ) )->export(
			array( Orders::cod( array( 'items' => array( array( 'name' => 'x', 'quantity' => 1 ) ) ) ), $order ),
			BuiltInFormats::create()['steadfast']
		);
		$this->assertSame( array( Exporter::WARN_ZERO_WEIGHT ), $result->warnings[1001] );
		$this->assertCount( 2, $result->rows );
	}

	public function test_missing_fields_warning_codes(): void {
		$order  = Orders::cod( array( 'id' => 5, 'name' => '', 'address_1' => '', 'address_2' => '', 'city' => '', 'state' => '', 'state_name' => '' ) );
		$result = ExporterFactory::create( $this->settings() )->export( array( $order ), BuiltInFormats::create()['steadfast'] );
		$this->assertSame(
			array( Exporter::WARN_MISSING_NAME, Exporter::WARN_MISSING_ADDRESS, Exporter::WARN_NO_DISTRICT ),
			$result->warnings[5]
		);
	}

	public function test_hooks_are_called_and_can_change_values(): void {
		$hooks = new RecordingHooks();
		$hooks->on(
			'woo_courier_export_district',
			static function ( $district ) {
				return 'Dhaka City';
			}
		);
		$hooks->on(
			'woo_courier_export_cod_amount',
			static function ( $amount ) {
				return $amount + 40;
			}
		);
		$hooks->on(
			'woo_courier_export_row',
			static function ( array $row ) {
				$row[] = 'extra';
				return $row;
			}
		);
		$hooks->on(
			'woo_courier_export_include_order',
			static function ( $include, $order ) {
				return 1002 !== $order->id;
			}
		);
		$settings = $this->settings();
		$result   = ExporterFactory::create( $settings, $hooks )->export(
			array( Orders::cod(), Orders::cod( array( 'id' => 1002 ) ) ),
			BuiltInFormats::create()['pathao'],
			ExporterFactory::options( $settings )
		);
		$row = $result->rows[0];
		$this->assertSame( 'Dhaka City', $row[6] );
		$this->assertSame( '1600', $row[14] );
		$this->assertSame( 'extra', end( $row ) );
		$this->assertSame( array( 1002 => Exporter::SKIP_FILTERED ), $result->skipped );
		$this->assertSame( 1600.0, $result->cod_total );
		foreach ( array( 'woo_courier_export_phone', 'woo_courier_export_weight', 'woo_courier_export_area', 'woo_courier_export_context' ) as $name ) {
			$this->assertContains( $name, $hooks->names() );
		}
	}

	public function test_phone_filter_can_replace_result_and_bad_values_are_ignored(): void {
		$hooks = new RecordingHooks();
		$hooks->on(
			'woo_courier_export_phone',
			static function ( $result, $order ) {
				return 1001 === $order->id ? PhoneResult::valid( 'x', '01999999990' ) : 'garbage';
			}
		);
		$result = ExporterFactory::create( $this->settings(), $hooks )->export(
			array( Orders::cod(), Orders::cod( array( 'id' => 7 ) ) ),
			BuiltInFormats::create()['steadfast']
		);
		$this->assertSame( '01999999990', $result->rows[0][2] );
		$this->assertSame( '01711234567', $result->rows[1][2] );
	}

	public function test_district_map_from_settings(): void {
		$rows = $this->export( 'pathao', array( Orders::cod( array( 'state' => 'BD-10', 'state_name' => 'Chattogram', 'city' => 'Agrabad' ) ) ), array( 'district_map' => 'BD-10 => Chittagong City', 'area_map' => 'agrabad => Agrabad Commercial' ) );
		$this->assertSame( 'Chittagong City', $rows[0]['recipient_city'] );
		$this->assertSame( 'Agrabad Commercial', $rows[0]['recipient_zone'] );
	}

	public function test_custom_format_and_csv_end_to_end(): void {
		$format   = TemplateFormat::from_definition( 'mini', 'Mini', "Ref = INV-{order.number}\nTo = {recipient.name|upper}\nCOD = {cod_amount|int}\nNote = {order.note|default:-}" );
		$exporter = ExporterFactory::create( $this->settings() );
		$result   = $exporter->export( array( Orders::cod( array( 'customer_note' => '=cmd|calc' ) ) ), $format, new ExportOptions() );
		$csv      = ( new CsvWriter( ',', "\n", false ) )->to_string( $result->headers, $result->rows );
		$this->assertSame( "Ref,To,COD,Note\nINV-1001,RAHIM UDDIN,1560,'=cmd|calc\n", $csv );
	}

	public function test_generator_input(): void {
		$orders = ( static function () {
			yield Orders::cod( array( 'id' => 1 ) );
			yield Orders::cod( array( 'id' => 2 ) );
		} )();
		$result = ExporterFactory::create( array() )->export( $orders, BuiltInFormats::create()['steadfast'] );
		$this->assertSame( array( 1, 2 ), $result->exported_ids );
	}

	public function test_writer_from_settings(): void {
		$writer = ExporterFactory::writer( array( 'delimiter' => 'tab', 'bom' => false ) );
		$this->assertSame( "a\tb\r\n", $writer->to_string( array(), array( array( 'a', 'b' ) ) ) );
	}
}
