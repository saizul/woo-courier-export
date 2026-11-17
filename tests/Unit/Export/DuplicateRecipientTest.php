<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Export\Exporter;
use Saizul\CourierExport\Export\ExporterFactory;
use Saizul\CourierExport\Export\ExportOptions;
use Saizul\CourierExport\Export\ExportResult;
use Saizul\CourierExport\Export\RecipientKey;
use Saizul\CourierExport\Format\BuiltInFormats;
use Saizul\CourierExport\Format\TemplateFormat;
use Saizul\CourierExport\Tests\Fixtures\Orders;
use Saizul\CourierExport\Tests\RecordingHooks;

final class DuplicateRecipientTest extends TestCase {

	/**
	 * Run an export with the given settings.
	 *
	 * @param array<int,\Saizul\CourierExport\Order\OrderData> $orders   Orders.
	 * @param array<string,mixed>                              $settings Settings.
	 */
	private function run_export( array $orders, array $settings = array(), ?RecordingHooks $hooks = null, string $format = 'steadfast' ): ExportResult {
		$format = BuiltInFormats::create()[ $format ];

		return ExporterFactory::create( $settings, $hooks )->export( $orders, $format, ExporterFactory::options( $settings ) );
	}

	/**
	 * Three orders: 1001 and 1003 share a phone typed in different ways, 1002 is someone else.
	 *
	 * @return array<int,\Saizul\CourierExport\Order\OrderData>
	 */
	private function batch(): array {
		return array(
			Orders::cod(),
			Orders::cod( array( 'id' => 1002, 'number' => '1002', 'phone' => '01911234567', 'alt_phone' => '' ) ),
			Orders::cod( array( 'id' => 1003, 'number' => '1003', 'phone' => '8801711234567' ) ),
		);
	}

	public function test_default_warns_on_every_order_of_the_group(): void {
		$result = $this->run_export( $this->batch() );

		$this->assertSame( array( 1001, 1002, 1003 ), $result->exported_ids );
		$this->assertSame( array( Exporter::WARN_DUPLICATE ), $result->warnings[1001] );
		$this->assertSame( array( Exporter::WARN_DUPLICATE ), $result->warnings[1003] );
		$this->assertArrayNotHasKey( 1002, $result->warnings );
		$this->assertSame( array( 1003 => 1001 ), $result->duplicates );
		$this->assertSame( array( array( 1001, 1003 ) ), $result->duplicate_groups() );
	}

	public function test_skip_keeps_only_the_first_order(): void {
		$result = $this->run_export( $this->batch(), array( 'duplicate_orders' => 'skip' ) );

		$this->assertSame( array( 1001, 1002 ), $result->exported_ids );
		$this->assertSame( array( 1003 => Exporter::SKIP_DUPLICATE ), $result->skipped );
		$this->assertSame( array( 1003 => 1001 ), $result->duplicates );
		$this->assertSame( array(), $result->warnings );
		$this->assertSame( 3120.0, $result->cod_total );
	}

	public function test_allow_exports_without_warnings_but_still_records_groups(): void {
		$result = $this->run_export( $this->batch(), array( 'duplicate_orders' => 'allow' ) );

		$this->assertCount( 3, $result->rows );
		$this->assertSame( array(), $result->warnings );
		$this->assertSame( array( 1003 => 1001 ), $result->duplicates );
	}

	public function test_three_orders_for_one_recipient_point_at_the_first(): void {
		$orders   = $this->batch();
		$orders[] = Orders::cod( array( 'id' => 1004, 'number' => '1004', 'phone' => '০১৭১১২৩৪৫৬৭' ) );
		$result   = $this->run_export( $orders, array( 'duplicate_orders' => 'skip' ) );

		$this->assertSame( array( 1003 => 1001, 1004 => 1001 ), $result->duplicates );
		$this->assertSame( array( array( 1001, 1003, 1004 ) ), $result->duplicate_groups() );
	}

	public function test_phone_and_address_match_ignores_other_addresses(): void {
		$orders = array(
			Orders::cod(),
			Orders::cod( array( 'id' => 1002, 'number' => '1002', 'address_1' => 'House 99, Road 1' ) ),
			Orders::cod( array( 'id' => 1003, 'number' => '1003', 'address_1' => 'house 12 road-5', 'address_2' => 'BLOCK C' ) ),
		);
		$result = $this->run_export( $orders, array( 'duplicate_match' => 'phone_address' ) );

		$this->assertSame( array( 1003 => 1001 ), $result->duplicates );
		$this->assertArrayNotHasKey( 1002, $result->warnings );
	}

	public function test_skipped_orders_do_not_start_a_group(): void {
		$orders = array(
			Orders::cod( array( 'meta' => array( '_woo_courier_export_exported' => 'redx' ) ) ),
			Orders::cod( array( 'id' => 1002, 'number' => '1002' ) ),
		);
		$result = $this->run_export( $orders, array( 'skip_exported' => true ) );

		$this->assertSame( array( 1002 ), $result->exported_ids );
		$this->assertSame( array(), $result->duplicates );
	}

	public function test_orders_without_a_usable_phone_are_not_compared(): void {
		$orders = array(
			Orders::cod( array( 'phone' => '' ) ),
			Orders::cod( array( 'id' => 1002, 'phone' => '' ) ),
			Orders::cod( array( 'id' => 1003, 'phone' => 'n/a 123' ) ),
			Orders::cod( array( 'id' => 1004, 'phone' => 'n/a 123' ) ),
		);
		$result = $this->run_export( $orders );

		$this->assertSame( array(), $result->duplicates );
	}

	public function test_invalid_but_identical_phones_are_still_matched(): void {
		$orders = array(
			Orders::cod( array( 'phone' => '02-9123456' ) ),
			Orders::cod( array( 'id' => 1002, 'phone' => '029123456' ) ),
		);
		$result = $this->run_export( $orders );

		$this->assertSame( array( 1002 => 1001 ), $result->duplicates );
		$this->assertSame( array( Exporter::WARN_INVALID_PHONE, Exporter::WARN_DUPLICATE ), $result->warnings[1002] );
	}

	public function test_duplicate_of_is_available_in_templates(): void {
		$format = TemplateFormat::from_definition( 'test', 'Test', "Invoice = {order.number}\nNote = {recipient.duplicate_of|prefix:\"Same customer as #\"}" );
		$result = ExporterFactory::create( array() )->export(
			array( Orders::cod( array( 'number' => 'WC-1001' ) ), Orders::cod( array( 'id' => 1002, 'number' => 'WC-1002' ) ) ),
			$format,
			ExporterFactory::options( array( 'duplicate_orders' => 'allow' ) )
		);

		$this->assertSame( array( 'WC-1001', '' ), $result->rows[0] );
		$this->assertSame( array( 'WC-1002', 'Same customer as #WC-1001' ), $result->rows[1] );
	}

	public function test_duplicate_key_filter_can_change_or_disable_the_check(): void {
		$hooks = new RecordingHooks();
		$hooks->on(
			'woo_courier_export_duplicate_key',
			static function ( $key, $order ) {
				return 1003 === $order->id ? '' : $key;
			}
		);
		$result = $this->run_export( $this->batch(), array(), $hooks );

		$this->assertSame( array(), $result->duplicates );
		$this->assertContains( 'woo_courier_export_duplicate_key', $hooks->names() );

		$hooks = new RecordingHooks();
		$hooks->on(
			'woo_courier_export_duplicate_key',
			static function () {
				return 'everyone';
			}
		);
		$result = $this->run_export( $this->batch(), array( 'duplicate_orders' => 'skip' ), $hooks );
		$this->assertSame( array( 1001 ), $result->exported_ids );
	}

	public function test_non_scalar_filter_result_disables_the_check(): void {
		$hooks = new RecordingHooks();
		$hooks->on(
			'woo_courier_export_duplicate_key',
			static function () {
				return array( 'x' );
			}
		);
		$this->assertSame( array(), $this->run_export( $this->batch(), array(), $hooks )->duplicates );
	}

	public function test_options_from_array(): void {
		$options = ExportOptions::from_array( array() );
		$this->assertSame( ExportOptions::DUPLICATES_WARN, $options->duplicates );
		$this->assertSame( ExportOptions::DUPLICATE_MATCH_PHONE, $options->duplicate_match );

		$options = ExportOptions::from_array(
			array(
				'duplicates'      => 'skip',
				'duplicate_match' => 'phone_address',
			)
		);
		$this->assertSame( ExportOptions::DUPLICATES_SKIP, $options->duplicates );
		$this->assertSame( ExportOptions::DUPLICATE_MATCH_PHONE_ADDRESS, $options->duplicate_match );

		$options = ExportOptions::from_array(
			array(
				'duplicates'      => 'explode',
				'duplicate_match' => 'name',
			)
		);
		$this->assertSame( ExportOptions::DUPLICATES_WARN, $options->duplicates );
		$this->assertSame( ExportOptions::DUPLICATE_MATCH_PHONE, $options->duplicate_match );
	}

	public function test_recipient_key(): void {
		$valid = array(
			'phone'             => '01711234567',
			'phone_raw'         => '+880 1711-234567',
			'phone_valid'       => true,
			'address_with_area' => 'House 12, Road-5; Mirpur, DHAKA',
		);
		$this->assertSame( '01711234567', RecipientKey::from_context( array( 'recipient' => $valid ) ) );
		$this->assertSame(
			'01711234567|house 12 road 5 mirpur dhaka',
			RecipientKey::from_context( array( 'recipient' => $valid ), ExportOptions::DUPLICATE_MATCH_PHONE_ADDRESS )
		);
		$this->assertSame( '', RecipientKey::from_context( array() ) );
		$this->assertSame( '', RecipientKey::phone( array( 'phone_raw' => '12345' ) ) );
		$this->assertSame( '029123456', RecipientKey::phone( array( 'phone_raw' => '02-912 3456' ) ) );
		$this->assertSame( 'বাড়ি ১২ মিরপুর', RecipientKey::address( '  বাড়ি ১২,  মিরপুর ' ) );
	}

	public function test_warning_codes_are_not_repeated(): void {
		$result = new ExportResult();
		$result->warn( 1, Exporter::WARN_DUPLICATE );
		$result->warn( 1, Exporter::WARN_DUPLICATE );
		$result->warn( 1, Exporter::WARN_ZERO_WEIGHT );

		$this->assertSame( array( Exporter::WARN_DUPLICATE, Exporter::WARN_ZERO_WEIGHT ), $result->warnings[1] );
	}
}
