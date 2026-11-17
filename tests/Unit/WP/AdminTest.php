<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\WP;

use Saizul\CourierExport\Settings\SettingsSchema;
use Saizul\CourierExport\Tests\WpTestCase;
use Saizul\CourierExport\WP\Admin\BulkAction;
use Saizul\CourierExport\WP\Admin\DownloadController;
use Saizul\CourierExport\WP\Admin\SettingsTab;
use Saizul\CourierExport\WP\ExportService;
use Saizul\CourierExport\WP\Settings;
use WpDieException;
use WpStubs;

final class AdminTest extends WpTestCase {

	private function service(): ExportService {
		return new ExportService( new Settings() );
	}

	public function test_bulk_actions_registered_on_both_screens(): void {
		( new BulkAction( $this->service() ) )->register();
		foreach ( array( 'edit-shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			$this->assertArrayHasKey( 'bulk_actions-' . $screen, WpStubs::$filters );
			$this->assertArrayHasKey( 'handle_bulk_actions-' . $screen, WpStubs::$filters );
		}
	}

	public function test_add_actions(): void {
		$actions = ( new BulkAction( $this->service() ) )->add_actions( array( 'trash' => 'Move to Trash' ) );
		$this->assertSame( 'Export to Steadfast CSV', $actions['woo_courier_export_steadfast'] );
		$this->assertArrayHasKey( 'woo_courier_export_generic', $actions );
		$this->assertArrayHasKey( 'trash', $actions );
	}

	public function test_add_actions_requires_capability(): void {
		WpStubs::$can = false;
		$this->assertSame( array(), ( new BulkAction( $this->service() ) )->add_actions( array() ) );
	}

	public function test_handle_ignores_other_actions(): void {
		$this->assertSame( 'back', ( new BulkAction( $this->service() ) )->handle( 'back', 'trash', array( 1 ) ) );
		$this->assertSame( 'back', ( new BulkAction( $this->service() ) )->handle( 'back', 'woo_courier_export_unknown', array( 1 ) ) );
		$this->assertSame( 'back', ( new BulkAction( $this->service() ) )->handle( 'back', 'woo_courier_export_redx', array( 'x' ) ) );
	}

	public function test_handle_creates_user_bound_job(): void {
		$url = ( new BulkAction( $this->service() ) )->handle( 'back', 'woo_courier_export_redx', array( '12', 13, 'bad' ) );
		$this->assertStringContainsString( 'admin-post.php?action=woo_courier_export_download&job=', $url );
		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertCount( 1, WpStubs::$transients );
		$job = reset( WpStubs::$transients );
		$this->assertSame( array( 12, 13 ), $job['ids'] );
		$this->assertSame( 'redx', $job['format'] );
		$this->assertSame( 1, $job['user'] );
	}

	public function test_handle_denies_without_capability(): void {
		WpStubs::$can = false;
		$this->expectException( WpDieException::class );
		( new BulkAction( $this->service() ) )->handle( 'back', 'woo_courier_export_redx', array( 1 ) );
	}

	public function test_download_denies_without_capability(): void {
		WpStubs::$can = false;
		$this->expectException( WpDieException::class );
		( new DownloadController( $this->service() ) )->download();
	}

	public function test_download_url_has_token_nonce(): void {
		$url = DownloadController::url( 'abc123' );
		$this->assertStringContainsString( 'job=abc123', $url );
		$this->assertStringContainsString( '_wpnonce=' . substr( md5( 'woo_courier_export_download_abc123' ), 0, 10 ), $url );
	}

	public function test_notice_shows_summary_once(): void {
		$controller = new DownloadController( $this->service() );
		WpStubs::$transients['woo_courier_export_notice_1'] = array(
			'count'    => 2,
			'skipped'  => array( 7 => 'invalid_phone' ),
			'warnings' => array( 8 => array( 'no_district', 'zero_weight' ) ),
		);
		ob_start();
		$controller->notice();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '2 orders exported.', $html );
		$this->assertStringContainsString( 'Order #7 skipped: invalid phone number.', $html );
		$this->assertStringContainsString( 'Order #8 exported with warnings: district not found, zero weight.', $html );
		$this->assertStringContainsString( 'notice-warning', $html );
		ob_start();
		$controller->notice();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_notice_names_the_first_order_for_duplicates(): void {
		WpStubs::$transients['woo_courier_export_notice_1'] = array(
			'count'      => 2,
			'skipped'    => array( 12 => 'duplicate' ),
			'warnings'   => array( 13 => array( 'duplicate' ) ),
			'duplicates' => array(
				12 => 10,
				13 => 10,
			),
		);
		ob_start();
		( new DownloadController( $this->service() ) )->notice();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Order #12 skipped: same recipient as order #10.', $html );
		$this->assertStringContainsString( 'Order #13 exported with warnings: another order in this export goes to the same recipient.', $html );
	}

	public function test_notice_escapes_output(): void {
		WpStubs::$transients['woo_courier_export_notice_1'] = array(
			'count'    => 1,
			'skipped'  => array( 3 => '<script>' ),
			'warnings' => array(),
		);
		ob_start();
		( new DownloadController( $this->service() ) )->notice();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_settings_tab_sanitises_option(): void {
		$tab   = new SettingsTab( new Settings(), $this->service() );
		$clean = $tab->sanitize_option(
			array(
				'delimiter'     => 'tab',
				'bom'           => 'no',
				'mark_exported' => 'yes',
				'cod_methods'   => array( 'cod', 'bad gateway' ),
				'evil'          => 'x',
			)
		);
		$this->assertSame( 'tab', $clean['delimiter'] );
		$this->assertSame( 'no', $clean['bom'] );
		$this->assertSame( 'yes', $clean['mark_exported'] );
		$this->assertSame( array( 'cod' ), $clean['cod_methods'] );
		$this->assertArrayNotHasKey( 'evil', $clean );
		$this->assertSame( SettingsSchema::to_storage( SettingsSchema::defaults() ), $tab->sanitize_option( 'garbage' ) );
		// Stored values read back to the same settings.
		$this->assertSame( SettingsSchema::sanitize( $clean ), SettingsSchema::sanitize( SettingsSchema::sanitize( $clean ) ) );
		$this->assertFalse( SettingsSchema::sanitize( $clean )['bom'] );
	}

	public function test_settings_tab_fields_use_single_option(): void {
		$fields = ( new SettingsTab( new Settings(), $this->service() ) )->fields();
		$ids    = array();
		foreach ( $fields as $field ) {
			if ( ! in_array( $field['type'], array( 'title', 'sectionend' ), true ) ) {
				$ids[] = $field['id'];
				$this->assertStringStartsWith( SettingsSchema::OPTION . '[', $field['id'] );
			}
		}
		$keys = array_map(
			static function ( $id ) {
				return substr( $id, strlen( SettingsSchema::OPTION ) + 1, -1 );
			},
			$ids
		);
		$this->assertSame( array(), array_diff( $keys, array_keys( SettingsSchema::defaults() ) ) );
		$this->assertContains( 'template_steadfast', $keys );
		$gateways = array_values( array_filter( $fields, static function ( $f ) {
			return isset( $f['id'] ) && SettingsSchema::OPTION . '[cod_methods]' === $f['id'];
		} ) )[0]['options'];
		$this->assertSame( 'bKash (bkash)', $gateways['bkash'] );
		$statuses = array_values( array_filter( $fields, static function ( $f ) {
			return isset( $f['id'] ) && SettingsSchema::OPTION . '[status_after_export]' === $f['id'];
		} ) )[0]['options'];
		$this->assertSame( array( '', 'pending', 'processing', 'completed' ), array_keys( $statuses ) );
		$tabs = ( new SettingsTab( new Settings(), $this->service() ) )->add_tab( array() );
		$this->assertSame( array( 'courier_export' => 'Courier export' ), $tabs );
	}
}
