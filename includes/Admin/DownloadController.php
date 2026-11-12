<?php
/**
 * Streams export files.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP\Admin;

use Saizul\CourierExport\WP\ExportService;
use Saizul\CourierExport\WP\Plugin;

/**
 * Handles admin-post.php?action=woo_courier_export_download.
 */
final class DownloadController {

	public const ACTION        = 'woo_courier_export_download';
	public const NOTICE_PREFIX = 'woo_courier_export_notice_';

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
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Nonce-protected download URL for a job.
	 *
	 * @param string $token Job token.
	 */
	public static function url( string $token ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'job'    => $token,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $token
		);
	}

	/**
	 * Stream the CSV for a job.
	 */
	public function download(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to export orders.', 'woo-courier-export' ), '', array( 'response' => 403 ) );
		}
		$token = isset( $_GET['job'] ) ? sanitize_key( wp_unslash( $_GET['job'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below with the token-bound nonce.
		check_admin_referer( self::ACTION . '_' . $token );

		$job = '' !== $token ? get_transient( BulkAction::JOB_PREFIX . $token ) : false;
		delete_transient( BulkAction::JOB_PREFIX . $token );
		if ( ! is_array( $job ) || get_current_user_id() !== (int) $job['user'] ) {
			wp_die( esc_html__( 'This export link has expired. Select the orders and run the bulk action again.', 'woo-courier-export' ), '', array( 'response' => 410 ) );
		}

		$format = (string) $job['format'];
		if ( ! $this->service->formats()->has( $format ) ) {
			wp_die( esc_html__( 'Unknown export format.', 'woo-courier-export' ), '', array( 'response' => 400 ) );
		}

		$orders = $this->service->load_orders( (array) $job['ids'] );
		$result = $this->service->export( $orders, $format );
		$this->remember_summary( $result->count(), $result->skipped, $result->warnings );

		$this->send( $this->service->filename( $format ), $result->headers, $result->rows );
	}

	/**
	 * Send the file and stop.
	 *
	 * @param string              $filename File name.
	 * @param string[]            $headers  Header row.
	 * @param array<int,string[]> $rows     Rows.
	 */
	private function send( string $filename, array $headers, array $rows ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$stream = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a download.
		if ( false !== $stream ) {
			$this->service->writer()->write( $stream, $headers, $rows );
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming a download.
		}
		exit;
	}

	/**
	 * Keep a summary to show on the next admin page load.
	 *
	 * @param int                 $count    Exported rows.
	 * @param array<int,string>   $skipped  Skipped orders.
	 * @param array<int,string[]> $warnings Warnings per order.
	 */
	private function remember_summary( int $count, array $skipped, array $warnings ): void {
		set_transient(
			self::NOTICE_PREFIX . get_current_user_id(),
			array(
				'count'    => $count,
				'skipped'  => $skipped,
				'warnings' => $warnings,
			),
			300
		);
	}

	/**
	 * Show the summary of the last export.
	 */
	public function notice(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		$key     = self::NOTICE_PREFIX . get_current_user_id();
		$summary = get_transient( $key );
		if ( ! is_array( $summary ) ) {
			return;
		}
		delete_transient( $key );

		$labels = ExportService::reason_labels();
		$lines  = array();
		foreach ( (array) $summary['skipped'] as $id => $reason ) {
			$lines[] = sprintf(
				/* translators: 1: order id, 2: reason. */
				__( 'Order #%1$d skipped: %2$s.', 'woo-courier-export' ),
				$id,
				$labels[ $reason ] ?? $reason
			);
		}
		foreach ( (array) $summary['warnings'] as $id => $codes ) {
			$text = array();
			foreach ( (array) $codes as $code ) {
				$text[] = $labels[ $code ] ?? $code;
			}
			$lines[] = sprintf(
				/* translators: 1: order id, 2: comma-separated problems. */
				__( 'Order #%1$d exported with warnings: %2$s.', 'woo-courier-export' ),
				$id,
				implode( ', ', $text )
			);
		}

		$class = array() === $lines ? 'notice-success' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of orders. */
				_n( '%d order exported.', '%d orders exported.', (int) $summary['count'], 'woo-courier-export' ),
				(int) $summary['count']
			)
		);
		echo '</p>';
		if ( array() !== $lines ) {
			echo '<ul style="list-style:disc;margin-left:2em">';
			foreach ( array_slice( $lines, 0, 50 ) as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
}
