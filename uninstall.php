<?php
/**
 * Uninstall routine.
 *
 * Settings are removed only when "Delete settings on uninstall" is enabled.
 * Export markers stored on orders are left in place: they belong to the
 * orders and are removed with them.
 *
 * @package Saizul\CourierExport
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$woo_courier_export_settings = get_option( 'woo_courier_export_settings', array() );

if ( ! is_array( $woo_courier_export_settings ) || empty( $woo_courier_export_settings['delete_on_uninstall'] ) ) {
	return;
}

delete_option( 'woo_courier_export_settings' );

// Short-lived download jobs and notices expire on their own; remove any left over.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_woo_courier_export_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_woo_courier_export_' ) . '%'
	)
);
