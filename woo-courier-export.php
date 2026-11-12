<?php
/**
 * Plugin Name:          Courier Export for WooCommerce
 * Plugin URI:           https://github.com/saizul/woo-courier-export
 * Description:          Bulk-export WooCommerce orders to CSV files for Bangladeshi couriers: Steadfast, Pathao, RedX or your own column template.
 * Version:              0.1.0
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Saizul Amin
 * Author URI:           https://saizul.com
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          woo-courier-export
 * Domain Path:          /languages
 * WC requires at least: 7.1
 * WC tested up to:      9.4
 *
 * @package Saizul\CourierExport
 */

defined( 'ABSPATH' ) || exit;

if ( PHP_VERSION_ID < 70400 ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Courier Export for WooCommerce requires PHP 7.4 or newer.', 'woo-courier-export' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'WOO_COURIER_EXPORT_FILE', __FILE__ );
define( 'WOO_COURIER_EXPORT_VERSION', '0.1.0' );

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// PSR-4 autoloader for installs without Composer (the release zip).
	spl_autoload_register(
		static function ( $class_name ) {
			$prefixes = array(
				'Saizul\\CourierExport\\WP\\' => __DIR__ . '/includes/',
				'Saizul\\CourierExport\\'     => __DIR__ . '/src/',
			);
			foreach ( $prefixes as $prefix => $dir ) {
				if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
					continue;
				}
				$file = $dir . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
				return;
			}
		}
	);
}

// Declare compatibility with High-Performance Order Storage (custom order tables).
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

\Saizul\CourierExport\WP\Plugin::boot( __FILE__ );
