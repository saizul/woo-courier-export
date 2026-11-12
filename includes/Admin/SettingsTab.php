<?php
/**
 * WooCommerce settings tab.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP\Admin;

use Saizul\CourierExport\Format\BuiltInFormats;
use Saizul\CourierExport\Settings\SettingsSchema;
use Saizul\CourierExport\WP\ExportService;
use Saizul\CourierExport\WP\Settings;

/**
 * WooCommerce > Settings > Courier export.
 *
 * Uses the WooCommerce settings API, which checks the "woocommerce-settings"
 * nonce and the manage_woocommerce capability before saving. Every value is
 * then passed through {@see SettingsSchema::sanitize()} by a
 * pre_update_option filter, so values written by other code are sanitised too.
 */
final class SettingsTab {

	public const TAB = 'courier_export';

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
	 * @param Settings      $settings Settings.
	 * @param ExportService $service  Export service.
	 */
	public function __construct( Settings $settings, ExportService $service ) {
		$this->settings = $settings;
		$this->service  = $service;
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_tab' ), 50 );
		add_action( 'woocommerce_settings_' . self::TAB, array( $this, 'output' ) );
		add_action( 'woocommerce_update_options_' . self::TAB, array( $this, 'save' ) );
		add_filter( 'pre_update_option_' . SettingsSchema::OPTION, array( $this, 'sanitize_option' ), 10, 1 );
	}

	/**
	 * URL of the tab.
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB );
	}

	/**
	 * Add the tab.
	 *
	 * @param array<string,string> $tabs Tabs.
	 * @return array<string,string>
	 */
	public function add_tab( $tabs ) {
		$tabs[ self::TAB ] = __( 'Courier export', 'woo-courier-export' );

		return $tabs;
	}

	/**
	 * Render the fields.
	 */
	public function output(): void {
		woocommerce_admin_fields( $this->fields() );
	}

	/**
	 * Save the fields.
	 */
	public function save(): void {
		woocommerce_update_options( $this->fields() );
		$this->settings->flush();
	}

	/**
	 * Sanitise the whole option before it is stored.
	 *
	 * @param mixed $value New value.
	 * @return array<string,mixed>
	 */
	public function sanitize_option( $value ) {
		$errors = array();
		$clean  = SettingsSchema::sanitize( is_array( $value ) ? $value : array(), $errors );
		if ( array() !== $errors && class_exists( '\WC_Admin_Settings' ) ) {
			foreach ( $errors as $error ) {
				\WC_Admin_Settings::add_error(
					sprintf(
						/* translators: %s: template error. */
						__( 'Template not saved, the built-in columns are used instead. %s', 'woo-courier-export' ),
						$error
					)
				);
			}
		}

		return SettingsSchema::to_storage( $clean );
	}

	/**
	 * Field id for a setting key (stored inside the single option array).
	 *
	 * @param string $key Key.
	 */
	private static function id( string $key ): string {
		return SettingsSchema::OPTION . '[' . $key . ']';
	}

	/**
	 * Payment gateways for the COD methods field.
	 *
	 * @return array<string,string>
	 */
	private function gateway_options(): array {
		$options = array( 'cod' => __( 'Cash on delivery', 'woo-courier-export' ) );
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
				$options[ (string) $id ] = wp_strip_all_tags( (string) $gateway->get_method_title() ) . ' (' . $id . ')';
			}
		}

		return $options;
	}

	/**
	 * Order statuses for "status after export".
	 *
	 * @return array<string,string>
	 */
	private function status_options(): array {
		$options = array( '' => __( 'Do not change', 'woo-courier-export' ) );
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $key => $label ) {
				$options[ (string) preg_replace( '/^wc-/', '', $key ) ] = $label;
			}
		}

		return $options;
	}

	/**
	 * The template reference shown above the template fields.
	 */
	private function template_help(): string {
		$values  = 'order.number, order.id, order.date, order.status, order.total, order.subtotal, order.shipping_total, order.paid, '
			. 'order.payment_method, order.payment_method_title, order.note, order.email, order.item_count, order.item_summary, order.items, '
			. 'recipient.name, recipient.phone, recipient.phone_intl, recipient.phone_raw, recipient.alt_phone, recipient.address, '
			. 'recipient.address_with_area, recipient.address_1, recipient.address_2, recipient.city, recipient.area, recipient.district, '
			. 'recipient.area_or_district, recipient.postcode, cod_amount, weight_kg, weight_g, store.pathao_store_id, '
			. 'store.redx_pickup_store_id, meta.*';
		$filters = 'upper, lower, title, trim, single, truncate:N, number:DECIMALS, int, default:TEXT, replace:FROM:TO, digits, phone, '
			. 'phone_intl, date:FORMAT, join:SEP, first, grams, prefix:TEXT, suffix:TEXT';

		return sprintf(
			/* translators: 1: example line, 2: list of values, 3: list of filters. */
			__( 'One column per line: %1$s. Leave a template empty to use the built-in columns. Values: %2$s. Filters: %3$s.', 'woo-courier-export' ),
			'Header = {recipient.name|upper}',
			$values,
			$filters
		);
	}

	/**
	 * Settings fields.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function fields(): array {
		$defaults = SettingsSchema::defaults();
		$formats  = array();
		foreach ( $this->service->formats()->all() as $id => $format ) {
			$formats[ $id ] = $format->label();
		}
		$yes_no = static function ( string $key ) use ( $defaults ): string {
			return $defaults[ $key ] ? 'yes' : 'no';
		};
		$mono   = 'width:100%;max-width:720px;height:12em;font-family:monospace;';

		$fields = array(
			array(
				'type'  => 'title',
				'id'    => 'woo_courier_export_general',
				'title' => __( 'Courier export', 'woo-courier-export' ),
				'desc'  => __( 'Select orders on the Orders screen and choose "Export to ... CSV" in the bulk actions. Couriers change their upload templates from time to time: compare the columns with the sample file in your courier panel before your first upload.', 'woo-courier-export' ),
			),
			array(
				'type'    => 'multiselect',
				'id'      => self::id( 'enabled_formats' ),
				'title'   => __( 'Formats in bulk actions', 'woo-courier-export' ),
				'options' => $formats,
				'default' => $defaults['enabled_formats'],
				'class'   => 'wc-enhanced-select',
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'default_format' ),
				'title'   => __( 'Default format for WP-CLI', 'woo-courier-export' ),
				'options' => $formats,
				'default' => $defaults['default_format'],
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'delimiter' ),
				'title'   => __( 'CSV delimiter', 'woo-courier-export' ),
				'options' => array(
					','   => __( 'Comma (,)', 'woo-courier-export' ),
					';'   => __( 'Semicolon (;)', 'woo-courier-export' ),
					'tab' => __( 'Tab', 'woo-courier-export' ),
					'|'   => __( 'Pipe (|)', 'woo-courier-export' ),
				),
				'default' => $defaults['delimiter'],
			),
			array(
				'type'    => 'checkbox',
				'id'      => self::id( 'bom' ),
				'title'   => __( 'UTF-8 byte order mark', 'woo-courier-export' ),
				'desc'    => __( 'Add a BOM so Excel shows Bangla text correctly. Turn off if your courier rejects the file.', 'woo-courier-export' ),
				'default' => $yes_no( 'bom' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'woo_courier_export_general',
			),
			array(
				'type'  => 'title',
				'id'    => 'woo_courier_export_money',
				'title' => __( 'COD amount and weight', 'woo-courier-export' ),
				'desc'  => __( 'COD amount = order total minus what has been paid already. Orders paid in full online export 0.', 'woo-courier-export' ),
			),
			array(
				'type'    => 'multiselect',
				'id'      => self::id( 'cod_methods' ),
				'title'   => __( 'Cash on delivery methods', 'woo-courier-export' ),
				'options' => $this->gateway_options(),
				'default' => $defaults['cod_methods'],
				'class'   => 'wc-enhanced-select',
			),
			array(
				'type'    => 'text',
				'id'      => self::id( 'paid_meta_key' ),
				'title'   => __( 'Advance payment meta key', 'woo-courier-export' ),
				'desc'    => __( 'Order meta key that stores an advance (for example a delivery charge paid by bKash). It is subtracted from the total. Leave empty if you do not take advances.', 'woo-courier-export' ),
				'default' => $defaults['paid_meta_key'],
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'unpaid_non_cod' ),
				'title'   => __( 'Unpaid orders with other methods', 'woo-courier-export' ),
				'options' => array(
					'collect' => __( 'Collect the balance on delivery', 'woo-courier-export' ),
					'zero'    => __( 'Export 0', 'woo-courier-export' ),
				),
				'default' => $defaults['unpaid_non_cod'],
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'cod_rounding' ),
				'title'   => __( 'Rounding', 'woo-courier-export' ),
				'options' => array(
					'round' => __( 'Nearest taka', 'woo-courier-export' ),
					'ceil'  => __( 'Up', 'woo-courier-export' ),
					'floor' => __( 'Down', 'woo-courier-export' ),
					'none'  => __( 'No rounding (2 decimals)', 'woo-courier-export' ),
				),
				'default' => $defaults['cod_rounding'],
			),
			array(
				'type'              => 'number',
				'id'                => self::id( 'default_item_weight' ),
				'title'             => __( 'Weight per item without weight (kg)', 'woo-courier-export' ),
				'default'           => (string) $defaults['default_item_weight'],
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			),
			array(
				'type'              => 'number',
				'id'                => self::id( 'minimum_weight' ),
				'title'             => __( 'Minimum parcel weight (kg)', 'woo-courier-export' ),
				'default'           => (string) $defaults['minimum_weight'],
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'woo_courier_export_money',
			),
			array(
				'type'  => 'title',
				'id'    => 'woo_courier_export_behaviour',
				'title' => __( 'Export behaviour', 'woo-courier-export' ),
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'invalid_phone' ),
				'title'   => __( 'Invalid phone numbers', 'woo-courier-export' ),
				'desc'    => __( 'Numbers are normalised to 01XXXXXXXXX. Orders whose number cannot be normalised are...', 'woo-courier-export' ),
				'options' => array(
					'include' => __( 'Exported as typed, with a warning', 'woo-courier-export' ),
					'skip'    => __( 'Skipped', 'woo-courier-export' ),
				),
				'default' => $defaults['invalid_phone'],
			),
			array(
				'type'    => 'checkbox',
				'id'      => self::id( 'mark_exported' ),
				'title'   => __( 'Mark exported orders', 'woo-courier-export' ),
				'desc'    => __( 'Store the format and time on the order after each export.', 'woo-courier-export' ),
				'default' => $yes_no( 'mark_exported' ),
			),
			array(
				'type'    => 'checkbox',
				'id'      => self::id( 'skip_exported' ),
				'title'   => __( 'Skip orders exported before', 'woo-courier-export' ),
				'default' => $yes_no( 'skip_exported' ),
			),
			array(
				'type'    => 'checkbox',
				'id'      => self::id( 'add_order_note' ),
				'title'   => __( 'Add an order note', 'woo-courier-export' ),
				'default' => $yes_no( 'add_order_note' ),
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'status_after_export' ),
				'title'   => __( 'Change status after export', 'woo-courier-export' ),
				'options' => $this->status_options(),
				'default' => $defaults['status_after_export'],
			),
			array(
				'type' => 'sectionend',
				'id'   => 'woo_courier_export_behaviour',
			),
			array(
				'type'  => 'title',
				'id'    => 'woo_courier_export_couriers',
				'title' => __( 'Courier accounts', 'woo-courier-export' ),
				'desc'  => __( 'Values available to templates as {store.*}.', 'woo-courier-export' ),
			),
			array(
				'type'    => 'text',
				'id'      => self::id( 'pathao_store_id' ),
				'title'   => __( 'Pathao store id', 'woo-courier-export' ),
				'default' => '',
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'pathao_delivery_type' ),
				'title'   => __( 'Pathao delivery type', 'woo-courier-export' ),
				'options' => array(
					'48' => __( 'Normal delivery (48)', 'woo-courier-export' ),
					'12' => __( 'On-demand delivery (12)', 'woo-courier-export' ),
				),
				'default' => $defaults['pathao_delivery_type'],
			),
			array(
				'type'    => 'select',
				'id'      => self::id( 'pathao_item_type' ),
				'title'   => __( 'Pathao item type', 'woo-courier-export' ),
				'options' => array(
					'2' => __( 'Parcel (2)', 'woo-courier-export' ),
					'1' => __( 'Document (1)', 'woo-courier-export' ),
				),
				'default' => $defaults['pathao_item_type'],
			),
			array(
				'type'    => 'text',
				'id'      => self::id( 'redx_pickup_store_id' ),
				'title'   => __( 'RedX pickup store id', 'woo-courier-export' ),
				'default' => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'woo_courier_export_couriers',
			),
			array(
				'type'  => 'title',
				'id'    => 'woo_courier_export_locations',
				'title' => __( 'District and area mapping', 'woo-courier-export' ),
				'desc'  => __( 'One mapping per line: "store value => courier value". The district map is matched against the state code (BD-13), state name and city; the area map against the city field. Unmapped Bangladeshi states are converted to district names automatically.', 'woo-courier-export' ),
			),
			array(
				'type'        => 'textarea',
				'id'          => self::id( 'district_map' ),
				'title'       => __( 'District map', 'woo-courier-export' ),
				'css'         => $mono,
				'placeholder' => "BD-13 => Dhaka\nChittagong => Chattogram",
				'default'     => '',
			),
			array(
				'type'        => 'textarea',
				'id'          => self::id( 'area_map' ),
				'title'       => __( 'Area map', 'woo-courier-export' ),
				'css'         => $mono,
				'placeholder' => "Mirpur 10 => Mirpur\nSavar => Savar Sadar",
				'default'     => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'woo_courier_export_locations',
			),
			array(
				'type'  => 'title',
				'id'    => 'woo_courier_export_templates',
				'title' => __( 'Column templates', 'woo-courier-export' ),
				'desc'  => $this->template_help(),
			),
		);

		foreach ( BuiltInFormats::definitions() as $id => $definition ) {
			$fields[] = array(
				'type'        => 'textarea',
				'id'          => self::id( 'template_' . $id ),
				/* translators: %s: courier or template name. */
				'title'       => sprintf( __( '%s columns', 'woo-courier-export' ), $formats[ $id ] ?? $definition[0] ),
				'css'         => $mono . 'height:16em;',
				'placeholder' => $definition[1],
				'default'     => '',
			);
		}

		$fields[] = array(
			'type' => 'sectionend',
			'id'   => 'woo_courier_export_templates',
		);
		$fields[] = array(
			'type'  => 'title',
			'id'    => 'woo_courier_export_advanced',
			'title' => __( 'Advanced', 'woo-courier-export' ),
		);
		$fields[] = array(
			'type'    => 'checkbox',
			'id'      => self::id( 'delete_on_uninstall' ),
			'title'   => __( 'Delete settings on uninstall', 'woo-courier-export' ),
			'desc'    => __( 'Remove this plugin\'s settings when it is deleted. Export markers on orders are kept.', 'woo-courier-export' ),
			'default' => $yes_no( 'delete_on_uninstall' ),
		);
		$fields[] = array(
			'type' => 'sectionend',
			'id'   => 'woo_courier_export_advanced',
		);

		return apply_filters( 'woo_courier_export_settings_fields', $fields );
	}
}
