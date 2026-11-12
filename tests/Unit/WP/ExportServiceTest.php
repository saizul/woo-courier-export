<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\WP;

use Saizul\CourierExport\Format\FormatRegistry;
use Saizul\CourierExport\Format\TemplateFormat;
use Saizul\CourierExport\Settings\SettingsSchema;
use Saizul\CourierExport\Tests\WpTestCase;
use Saizul\CourierExport\WP\ExportService;
use Saizul\CourierExport\WP\Order\OrderMapper;
use Saizul\CourierExport\WP\Settings;
use WpStubs;

final class ExportServiceTest extends WpTestCase {

	private function service( array $settings = array() ): ExportService {
		WpStubs::$options[ SettingsSchema::OPTION ] = $settings;
		WpStubs::$options['woocommerce_weight_unit'] = 'g';
		return new ExportService( new Settings() );
	}

	public function test_export_marks_orders_and_adds_notes(): void {
		$order  = $this->order();
		$result = $this->service()->export( array( $order ), 'steadfast' );
		$this->assertSame( 1, $result->count() );
		$this->assertSame( '01911234567', $result->rows[0][2] );
		$this->assertSame( '2150', $result->rows[0][5] );
		$this->assertSame( 'steadfast', $order->meta[ OrderMapper::EXPORTED_META ] );
		$this->assertNotEmpty( $order->meta[ OrderMapper::EXPORTED_AT_META ] );
		$this->assertSame( array( 'Exported to a Steadfast CSV file.' ), $order->notes );
		$this->assertSame( 1, $order->saves );
		$names = array_column( WpStubs::$actions, 0 );
		$this->assertContains( 'woo_courier_export_before_export', $names );
		$this->assertContains( 'woo_courier_export_after_export', $names );
		$this->assertContains( 'woo_courier_export_order_exported', $names );
	}

	public function test_status_change_after_export(): void {
		$order = $this->order();
		$this->service( array( 'status_after_export' => 'completed' ) )->export( array( $order ), 'redx' );
		$this->assertSame( 'completed', $order->get_status() );
		$this->assertSame( array( 'Exported to a RedX CSV file.' ), $order->notes );
	}

	public function test_no_mark_leaves_order_untouched(): void {
		$order = $this->order();
		$this->service()->export( array( $order ), 'pathao', false );
		$this->assertArrayNotHasKey( OrderMapper::EXPORTED_META, $order->meta );
		$this->assertSame( array(), $order->notes );
		$this->assertSame( 0, $order->saves );
	}

	public function test_weight_uses_store_unit(): void {
		$result = $this->service( array( 'minimum_weight' => 0 ) )->export( array( $this->order() ), 'redx', false );
		$this->assertSame( '700', $result->rows[0][7] );
	}

	public function test_template_override_from_settings(): void {
		$service = $this->service( array( 'template_generic' => "Ref = {order.number}\nTo = {recipient.district}" ) );
		$result  = $service->export( array( $this->order() ), 'generic', false );
		$this->assertSame( array( 'Ref', 'To' ), $result->headers );
		$this->assertSame( array( 'WC-501', 'Dhaka' ), $result->rows[0] );
	}

	public function test_formats_filter_can_add_format(): void {
		add_filter(
			'woo_courier_export_formats',
			static function ( FormatRegistry $registry ) {
				$registry->add( TemplateFormat::from_definition( 'paperfly', 'Paperfly', 'Ref = {order.number}' ) );
				return $registry;
			}
		);
		$service = $this->service( array( 'enabled_formats' => array( 'steadfast' ) ) );
		$this->assertTrue( $service->formats()->has( 'paperfly' ) );
		$this->assertSame( array( 'steadfast', 'paperfly' ), array_keys( $service->enabled_formats() ) );
	}

	public function test_bad_formats_filter_return_is_ignored(): void {
		add_filter(
			'woo_courier_export_formats',
			static function () {
				return 'nope';
			}
		);
		$this->assertTrue( $this->service()->formats()->has( 'steadfast' ) );
	}

	public function test_load_orders_skips_unknown_ids_and_refunds(): void {
		$this->order();
		$this->order( array( 'id' => 9, 'type' => 'shop_order_refund' ) );
		$orders = $this->service()->load_orders( array( 501, 501, 9, 777, 0 ) );
		$this->assertCount( 1, $orders );
	}

	public function test_query_orders_uses_wc_get_orders(): void {
		$this->order();
		$this->order( array( 'id' => 502, 'status' => 'on-hold' ) );
		$orders = $this->service()->query_orders( array( 'status' => array( 'on-hold' ) ) );
		$this->assertCount( 1, $orders );
		$this->assertSame( 'shop_order', \WcStubs::$last_query['type'] );
		$this->assertSame( 'objects', \WcStubs::$last_query['return'] );
	}

	public function test_filename_and_writer(): void {
		add_filter(
			'woo_courier_export_csv_options',
			static function ( $options ) {
				$options['delimiter'] = ';';
				$options['bom']       = false;
				return $options;
			}
		);
		$service = $this->service();
		$this->assertMatchesRegularExpression( '/^pathao-orders-\d{4}-\d{2}-\d{2}-\d{6}\.csv$/', $service->filename( 'pathao' ) );
		$this->assertSame( "a;b\r\n", $service->writer()->to_string( array( 'a', 'b' ), array() ) );
	}

	public function test_reason_labels_cover_codes(): void {
		$labels = ExportService::reason_labels();
		$this->assertArrayHasKey( 'invalid_phone', $labels );
		$this->assertArrayHasKey( 'no_district', $labels );
	}

	public function test_settings_reads_and_sanitises_option(): void {
		WpStubs::$options[ SettingsSchema::OPTION ] = array( 'delimiter' => ';', 'cod_rounding' => 'bad' );
		$settings = new Settings();
		$this->assertSame( ';', $settings->get( 'delimiter' ) );
		$this->assertSame( 'round', $settings->get( 'cod_rounding' ) );
		$this->assertNull( $settings->get( 'nope' ) );
		WpStubs::$options[ SettingsSchema::OPTION ] = 'corrupt';
		$settings->flush();
		$this->assertSame( SettingsSchema::defaults(), $settings->all() );
	}
}
