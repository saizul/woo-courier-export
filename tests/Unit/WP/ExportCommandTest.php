<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\WP;

use Saizul\CourierExport\Settings\SettingsSchema;
use Saizul\CourierExport\Tests\WpTestCase;
use Saizul\CourierExport\WP\Cli\ExportCommand;
use Saizul\CourierExport\WP\ExportService;
use Saizul\CourierExport\WP\Order\OrderMapper;
use Saizul\CourierExport\WP\Settings;
use WP_CLI;
use WpCliExit;
use WpStubs;

final class ExportCommandTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_CLI::reset();
	}

	private function command( array $settings = array() ): ExportCommand {
		WpStubs::$options[ SettingsSchema::OPTION ] = $settings;
		return new ExportCommand( new ExportService( new Settings() ) );
	}

	/**
	 * @return string[]
	 */
	private function messages( string $type ): array {
		$out = array();
		foreach ( WP_CLI::$log as $entry ) {
			if ( $type === $entry[0] ) {
				$out[] = $entry[1];
			}
		}
		return $out;
	}

	public function test_export_to_stdout_by_ids(): void {
		$order = $this->order();
		$this->command()->export( array( 'steadfast' ), array( 'ids' => '501' ) );
		$lines = $this->messages( 'line' );
		$this->assertCount( 1, $lines );
		$this->assertStringContainsString( "invoice,recipient_name,recipient_phone", $lines[0] );
		$this->assertStringContainsString( 'WC-501,Karim Ahmed,01911234567', $lines[0] );
		$this->assertStringContainsString( '1 order(s) exported, 0 skipped, COD total 2150.00.', $this->messages( 'success' )[0] );
		$this->assertSame( 'steadfast', $order->meta[ OrderMapper::EXPORTED_META ] );
	}

	public function test_export_to_file_with_query(): void {
		$this->order();
		$this->order( array( 'id' => 502, 'status' => 'on-hold' ) );
		$file = tempnam( sys_get_temp_dir(), 'wce' );
		$this->command()->export( array( 'generic' ), array( 'status' => 'processing', 'output' => $file, 'after' => '2026-11-01', 'before' => '2026-11-30', 'mark' => false ) );
		$csv = (string) file_get_contents( $file );
		unlink( $file );
		$this->assertSame( 2, substr_count( $csv, "\r\n" ) );
		$this->assertSame( '2026-11-01...2026-11-30', \WcStubs::$last_query['date_created'] );
		$this->assertArrayNotHasKey( OrderMapper::EXPORTED_META, \WcStubs::$orders[501]->meta );
	}

	public function test_dry_run_writes_nothing(): void {
		$order = $this->order();
		$this->command()->export( array( 'redx' ), array( 'ids' => '501', 'dry-run' => true ) );
		$this->assertSame( array(), $this->messages( 'line' ) );
		$this->assertArrayNotHasKey( OrderMapper::EXPORTED_META, $order->meta );
		$this->assertCount( 1, $this->messages( 'success' ) );
	}

	public function test_skip_exported_flag(): void {
		$this->order( array( 'meta' => array( OrderMapper::EXPORTED_META => 'redx' ) ) );
		$this->command()->export( array( 'redx' ), array( 'ids' => '501', 'skip-exported' => true ) );
		$this->assertSame( array( 'Order 501 skipped: filtered' ), $this->messages( 'warning' ) );
	}

	public function test_default_format_and_errors(): void {
		$this->expectException( WpCliExit::class );
		$this->command()->export( array( 'nope' ), array() );
	}

	public function test_invalid_date(): void {
		$this->expectException( WpCliExit::class );
		$this->command()->export( array( 'redx' ), array( 'after' => 'yesterday' ) );
	}

	public function test_no_orders_warns(): void {
		$this->command()->export( array( 'redx' ), array( 'status' => 'completed' ) );
		$this->assertSame( array( 'No orders matched.' ), $this->messages( 'warning' ) );
	}

	public function test_formats_and_phone(): void {
		$command = $this->command();
		$command->formats( array(), array( 'format' => 'json' ) );
		$this->assertSame( 'json', WP_CLI::$items[0][0] );
		$this->assertSame( array( 'steadfast', 'pathao', 'redx', 'generic' ), array_column( WP_CLI::$items[0][1], 'id' ) );
		$command->phone( array( '+880 1711-234567', 'abc' ) );
		$rows = WP_CLI::$items[1][1];
		$this->assertSame( '01711234567', $rows[0]['normalized'] );
		$this->assertSame( 'no', $rows[1]['valid'] );
	}
}
