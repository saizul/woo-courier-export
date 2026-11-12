<?php
/**
 * Orders list bulk actions.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP\Admin;

use Saizul\CourierExport\WP\ExportService;
use Saizul\CourierExport\WP\Plugin;

/**
 * Adds "Export to ... CSV" to the bulk actions of both orders screens
 * (legacy posts list and the HPOS orders page).
 *
 * The list tables verify their own bulk nonce before calling the handler. The
 * handler stores the selection in a short-lived, user-bound job and redirects
 * to a nonce-protected download URL, which keeps order ids out of long URLs
 * and works the same on both screens.
 */
final class BulkAction {

	public const PREFIX     = 'woo_courier_export_';
	public const JOB_PREFIX = 'woo_courier_export_job_';
	public const JOB_TTL    = 600;

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
		foreach ( array( 'edit-shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			add_filter( 'bulk_actions-' . $screen, array( $this, 'add_actions' ) );
			add_filter( 'handle_bulk_actions-' . $screen, array( $this, 'handle' ), 10, 3 );
		}
	}

	/**
	 * Add one action per enabled format.
	 *
	 * @param array<string,string> $actions Existing actions.
	 * @return array<string,string>
	 */
	public function add_actions( $actions ) {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return $actions;
		}
		foreach ( $this->service->enabled_formats() as $id => $format ) {
			$actions[ self::PREFIX . $id ] = sprintf(
				/* translators: %s: courier or template name. */
				__( 'Export to %s CSV', 'woo-courier-export' ),
				$format->label()
			);
		}

		return $actions;
	}

	/**
	 * Handle a bulk action.
	 *
	 * @param string           $redirect_to Redirect URL.
	 * @param string           $action      Action name.
	 * @param array<int,mixed> $ids         Selected order ids.
	 * @return string
	 */
	public function handle( $redirect_to, $action, $ids ) {
		if ( ! is_string( $action ) || 0 !== strpos( $action, self::PREFIX ) ) {
			return $redirect_to;
		}
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to export orders.', 'woo-courier-export' ), '', array( 'response' => 403 ) );
		}
		$format = substr( $action, strlen( self::PREFIX ) );
		if ( ! $this->service->formats()->has( $format ) ) {
			return $redirect_to;
		}
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( array() === $ids ) {
			return $redirect_to;
		}

		$token = self::create_job( $format, $ids, (string) $redirect_to );

		return DownloadController::url( $token );
	}

	/**
	 * Store a job and return its token.
	 *
	 * @param string $format      Format id.
	 * @param int[]  $ids         Order ids.
	 * @param string $return_url  Where the user came from.
	 */
	public static function create_job( string $format, array $ids, string $return_url = '' ): string {
		$token = strtolower( wp_generate_password( 20, false, false ) );
		set_transient(
			self::JOB_PREFIX . $token,
			array(
				'user'   => get_current_user_id(),
				'format' => $format,
				'ids'    => $ids,
				'return' => $return_url,
			),
			self::JOB_TTL
		);

		return $token;
	}
}
