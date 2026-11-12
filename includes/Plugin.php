<?php
/**
 * Plugin bootstrap.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP;

use Saizul\CourierExport\WP\Admin\BulkAction;
use Saizul\CourierExport\WP\Admin\DownloadController;
use Saizul\CourierExport\WP\Admin\SettingsTab;
use Saizul\CourierExport\WP\Cli\ExportCommand;

/**
 * Wires the export pipeline into WordPress and WooCommerce.
 */
final class Plugin {

	public const VERSION    = '0.1.0';
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Export service.
	 *
	 * @var ExportService
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param string $file Main plugin file.
	 */
	private function __construct( string $file ) {
		$this->file     = $file;
		$this->settings = new Settings();
		$this->service  = new ExportService( $this->settings );
	}

	/**
	 * Create the singleton and attach bootstrap hooks.
	 *
	 * @param string $file Main plugin file.
	 */
	public static function boot( string $file ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
			add_action( 'plugins_loaded', array( self::$instance, 'init' ), 20 );
			add_action( 'init', array( self::$instance, 'load_textdomain' ) );
		}

		return self::$instance;
	}

	/**
	 * The running instance, if booted.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Capability needed to export, filterable.
	 */
	public static function capability(): string {
		return (string) apply_filters( 'woo_courier_export_capability', self::CAPABILITY );
	}

	/**
	 * Register hooks once WooCommerce is loaded.
	 */
	public function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		if ( is_admin() ) {
			( new BulkAction( $this->service ) )->register();
			( new DownloadController( $this->service ) )->register();
			( new SettingsTab( $this->settings, $this->service ) )->register();
			add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'action_links' ) );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'courier-export', new ExportCommand( $this->service ) );
		}

		do_action( 'woo_courier_export_loaded', $this );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'woo-courier-export', false, dirname( plugin_basename( $this->file ) ) . '/languages' );
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( SettingsTab::url() ) . '">' . esc_html__( 'Settings', 'woo-courier-export' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Notice when WooCommerce is not active.
	 */
	public function missing_woocommerce_notice(): void {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Courier Export for WooCommerce needs WooCommerce to be installed and active.', 'woo-courier-export' ) . '</p></div>';
		}
	}

	/**
	 * Export service (for integrations).
	 */
	public function service(): ExportService {
		return $this->service;
	}

	/**
	 * Settings (for integrations).
	 */
	public function settings(): Settings {
		return $this->settings;
	}
}
