<?php
/**
 * WP-CLI command.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP\Cli;

use Saizul\CourierExport\Phone\PhoneNormalizer;
use Saizul\CourierExport\WP\ExportService;
use WP_CLI;

/**
 * Export WooCommerce orders to courier CSV files.
 *
 * ## EXAMPLES
 *
 *     # Processing orders to a Steadfast file
 *     $ wp courier-export export steadfast --status=processing --output=steadfast.csv
 *
 *     # Specific orders to stdout, without marking them
 *     $ wp courier-export export pathao --ids=1201,1202 --no-mark
 *
 *     # List the formats
 *     $ wp courier-export formats
 */
final class ExportCommand {

	/**
	 * Export service.
	 *
	 * @var ExportService
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param ExportService $service Export service.
	 */
	public function __construct( ExportService $service ) {
		$this->service = $service;
	}

	/**
	 * Export orders to a courier CSV file.
	 *
	 * ## OPTIONS
	 *
	 * [<format>]
	 * : Format id (steadfast, pathao, redx, generic or a custom one). Defaults to the setting.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated order ids. Overrides the query options.
	 *
	 * [--status=<status>]
	 * : Comma-separated order statuses.
	 * ---
	 * default: processing
	 * ---
	 *
	 * [--after=<date>]
	 * : Only orders created on or after this date (YYYY-MM-DD).
	 *
	 * [--before=<date>]
	 * : Only orders created on or before this date (YYYY-MM-DD).
	 *
	 * [--limit=<n>]
	 * : Maximum number of orders.
	 * ---
	 * default: 500
	 * ---
	 *
	 * [--output=<file>]
	 * : Write to this file instead of standard output.
	 *
	 * [--skip-exported]
	 * : Skip orders that were exported before.
	 *
	 * [--duplicates=<mode>]
	 * : Orders for the same recipient as an earlier order in this export (default: the setting).
	 * ---
	 * options:
	 *   - warn
	 *   - skip
	 *   - allow
	 * ---
	 *
	 * [--[no-]mark]
	 * : Mark exported orders (default: the "Mark exported orders" setting).
	 *
	 * [--dry-run]
	 * : Show the summary only; do not write a file or change orders.
	 *
	 * ## EXAMPLES
	 *
	 *     wp courier-export export redx --status=processing,on-hold --after=2026-11-01 --output=redx.csv
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string,string> $assoc_args Named arguments.
	 */
	public function export( $args, $assoc_args ): void {
		$format = isset( $args[0] ) ? (string) $args[0] : $this->default_format();
		if ( ! $this->service->formats()->has( $format ) ) {
			WP_CLI::error( sprintf( 'Unknown format "%s". Run "wp courier-export formats".', $format ) );
		}

		if ( ! empty( $assoc_args['ids'] ) ) {
			$ids    = array_filter( array_map( 'absint', explode( ',', (string) $assoc_args['ids'] ) ) );
			$orders = $this->service->load_orders( $ids );
		} else {
			$query  = array(
				'status' => array_map( 'trim', explode( ',', (string) ( $assoc_args['status'] ?? 'processing' ) ) ),
				'limit'  => max( 1, (int) ( $assoc_args['limit'] ?? 500 ) ),
			);
			$after  = isset( $assoc_args['after'] ) ? $this->date( (string) $assoc_args['after'] ) : '';
			$before = isset( $assoc_args['before'] ) ? $this->date( (string) $assoc_args['before'] ) : '';
			if ( '' !== $after && '' !== $before ) {
				$query['date_created'] = $after . '...' . $before;
			} elseif ( '' !== $after ) {
				$query['date_created'] = '>=' . $after;
			} elseif ( '' !== $before ) {
				$query['date_created'] = '<=' . $before;
			}
			$orders = $this->service->query_orders( $query );
		}

		if ( array() === $orders ) {
			WP_CLI::warning( 'No orders matched.' );
			return;
		}

		$dry_run = ! empty( $assoc_args['dry-run'] );
		$mark    = $dry_run ? false : ( isset( $assoc_args['mark'] ) ? (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'mark' ) : null );
		if ( ! empty( $assoc_args['skip-exported'] ) ) {
			add_filter(
				'woo_courier_export_include_order',
				static function ( $keep, $order ) {
					return $keep && empty( $order->meta['_woo_courier_export_exported'] );
				},
				10,
				2
			);
		}

		$overrides = array();
		if ( isset( $assoc_args['duplicates'] ) ) {
			$mode = (string) $assoc_args['duplicates'];
			if ( ! in_array( $mode, array( 'warn', 'skip', 'allow' ), true ) ) {
				WP_CLI::error( sprintf( 'Unknown --duplicates value "%s". Use warn, skip or allow.', $mode ) );
			}
			$overrides['duplicate_orders'] = $mode;
		}

		$result = $this->service->export( $orders, $format, $mark, $overrides );

		if ( ! $dry_run ) {
			$csv = $this->service->writer()->to_string( $result->headers, $result->rows );
			if ( ! empty( $assoc_args['output'] ) ) {
				$file = (string) $assoc_args['output'];
				if ( false === file_put_contents( $file, $csv ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI output file chosen by the user.
					WP_CLI::error( sprintf( 'Could not write %s.', $file ) );
				}
				WP_CLI::log( sprintf( 'Wrote %s', $file ) );
			} else {
				WP_CLI::line( rtrim( $csv, "\r\n" ) );
			}
		}

		foreach ( $result->skipped as $id => $reason ) {
			if ( isset( $result->duplicates[ $id ] ) ) {
				$reason .= sprintf( ' (same recipient as order %d)', $result->duplicates[ $id ] );
			}
			WP_CLI::warning( sprintf( 'Order %d skipped: %s', $id, $reason ) );
		}
		foreach ( $result->warnings as $id => $codes ) {
			WP_CLI::warning( sprintf( 'Order %d: %s', $id, implode( ', ', $codes ) ) );
		}
		WP_CLI::success( sprintf( '%d order(s) exported, %d skipped, COD total %s.', $result->count(), count( $result->skipped ), number_format( $result->cod_total, 2, '.', '' ) ) );
	}

	/**
	 * List the available formats.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string,string> $assoc_args Named arguments.
	 */
	public function formats( $args, $assoc_args ): void {
		$items = array();
		foreach ( $this->service->formats()->all() as $id => $format ) {
			$items[] = array(
				'id'      => $id,
				'label'   => $format->label(),
				'columns' => implode( ', ', $format->headers() ),
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'id', 'label', 'columns' ) );
	}

	/**
	 * Normalise Bangladeshi phone numbers.
	 *
	 * ## OPTIONS
	 *
	 * <number>...
	 * : One or more phone numbers.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp courier-export phone "+880 1711-234567" 1811234567
	 *
	 * @param string[] $args Positional arguments.
	 */
	public function phone( $args ): void {
		$phones = new PhoneNormalizer();
		$items  = array();
		foreach ( $args as $raw ) {
			$result  = $phones->normalize( $raw );
			$items[] = array(
				'input'      => $raw,
				'normalized' => (string) $result->local(),
				'valid'      => $result->is_valid() ? 'yes' : 'no',
				'reason'     => (string) $result->reason(),
			);
		}
		WP_CLI\Utils\format_items( 'table', $items, array( 'input', 'normalized', 'valid', 'reason' ) );
	}

	/**
	 * Default format from the settings.
	 */
	private function default_format(): string {
		$settings = \Saizul\CourierExport\WP\Plugin::instance();
		$default  = $settings ? (string) $settings->settings()->get( 'default_format' ) : 'steadfast';

		return '' !== $default ? $default : 'steadfast';
	}

	/**
	 * Validate a YYYY-MM-DD date.
	 *
	 * @param string $value Date.
	 */
	private function date( string $value ): string {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			WP_CLI::error( sprintf( 'Invalid date "%s"; use YYYY-MM-DD.', $value ) );
		}

		return $value;
	}
}
