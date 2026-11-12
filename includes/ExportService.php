<?php
/**
 * Runs exports inside WordPress.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP;

use Saizul\CourierExport\Export\CsvWriter;
use Saizul\CourierExport\Export\Exporter;
use Saizul\CourierExport\Export\ExporterFactory;
use Saizul\CourierExport\Export\ExportResult;
use Saizul\CourierExport\Format\BuiltInFormats;
use Saizul\CourierExport\Format\CourierFormat;
use Saizul\CourierExport\Format\FormatRegistry;
use Saizul\CourierExport\WP\Order\OrderMapper;
use WC_Order;

/**
 * Loads orders, runs the exporter, marks orders and builds file names.
 */
final class ExportService {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Format registry (built lazily so filters registered late are honoured).
	 *
	 * @var FormatRegistry|null
	 */
	private $registry = null;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Available formats, after the `woo_courier_export_formats` filter.
	 */
	public function formats(): FormatRegistry {
		if ( null === $this->registry ) {
			$settings  = $this->settings->all();
			$overrides = array();
			foreach ( array_keys( BuiltInFormats::definitions() ) as $id ) {
				$overrides[ $id ] = (string) $settings[ 'template_' . $id ];
			}
			$labels   = array(
				BuiltInFormats::STEADFAST => __( 'Steadfast', 'woo-courier-export' ),
				BuiltInFormats::PATHAO    => __( 'Pathao', 'woo-courier-export' ),
				BuiltInFormats::REDX      => __( 'RedX', 'woo-courier-export' ),
				BuiltInFormats::GENERIC   => __( 'Generic template', 'woo-courier-export' ),
			);
			$registry = new FormatRegistry( BuiltInFormats::create( $overrides, $labels ) );

			/**
			 * Add, replace or remove export formats.
			 *
			 * @param FormatRegistry $registry Registry with the built-in formats.
			 */
			$filtered       = apply_filters( 'woo_courier_export_formats', $registry );
			$this->registry = $filtered instanceof FormatRegistry ? $filtered : $registry;
		}

		return $this->registry;
	}

	/**
	 * Formats enabled in the settings (all of them when none are selected).
	 *
	 * @return array<string,CourierFormat>
	 */
	public function enabled_formats(): array {
		$enabled = (array) $this->settings->get( 'enabled_formats' );
		$all     = $this->formats()->all();
		if ( array() === $enabled ) {
			return $all;
		}

		$out = array();
		foreach ( $all as $id => $format ) {
			if ( in_array( $id, $enabled, true ) || ! array_key_exists( $id, BuiltInFormats::definitions() ) ) {
				$out[ $id ] = $format;
			}
		}

		return $out;
	}

	/**
	 * Build the configured exporter.
	 */
	public function exporter(): Exporter {
		return ExporterFactory::create( $this->settings->all(), new WpHooks() );
	}

	/**
	 * Order mapper.
	 */
	public function mapper(): OrderMapper {
		return new OrderMapper( (string) $this->settings->get( 'paid_meta_key' ), (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
	}

	/**
	 * Load orders by id, skipping ids that are not orders.
	 *
	 * @param int[] $ids Order ids.
	 * @return WC_Order[]
	 */
	public function load_orders( array $ids ): array {
		$orders = array();
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			$order = $id > 0 ? wc_get_order( $id ) : false;
			if ( $order instanceof WC_Order && 'shop_order_refund' !== $order->get_type() ) {
				$orders[] = $order;
			}
		}

		return $orders;
	}

	/**
	 * Query orders with wc_get_orders() (HPOS compatible).
	 *
	 * @param array<string,mixed> $args wc_get_orders() arguments.
	 * @return WC_Order[]
	 */
	public function query_orders( array $args ): array {
		$args   = array_merge(
			array(
				'type'    => 'shop_order',
				'limit'   => 100,
				'orderby' => 'date',
				'order'   => 'ASC',
				'return'  => 'objects',
			),
			$args
		);
		$orders = wc_get_orders( $args );

		return is_array( $orders ) ? array_values(
			array_filter(
				$orders,
				static function ( $order ): bool {
					return $order instanceof WC_Order;
				}
			)
		) : array();
	}

	/**
	 * Export orders.
	 *
	 * @param WC_Order[] $orders    Orders.
	 * @param string     $format_id Format id.
	 * @param bool|null  $mark      Mark orders as exported (null: use the setting).
	 * @return ExportResult
	 */
	public function export( array $orders, string $format_id, ?bool $mark = null ): ExportResult {
		$format  = $this->formats()->get( $format_id );
		$mapper  = $this->mapper();
		$options = ExporterFactory::options( $this->settings->all() );
		$by_id   = array();
		$data    = array();
		foreach ( $orders as $order ) {
			$by_id[ $order->get_id() ] = $order;
			$data[]                    = $mapper->map( $order );
		}

		do_action( 'woo_courier_export_before_export', array_keys( $by_id ), $format_id );
		$result = $this->exporter()->export( $data, $format, $options );

		$mark = null === $mark ? (bool) $this->settings->get( 'mark_exported' ) : $mark;
		foreach ( $result->exported_ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$this->after_export( $by_id[ $id ], $format, $mark );
			}
		}
		do_action( 'woo_courier_export_after_export', $result, $format_id );

		return $result;
	}

	/**
	 * Mark, annotate and optionally move an exported order.
	 *
	 * @param WC_Order      $order  Order.
	 * @param CourierFormat $format Format.
	 * @param bool          $mark   Whether to store the exported marker.
	 */
	private function after_export( WC_Order $order, CourierFormat $format, bool $mark ): void {
		$changed = false;
		if ( $mark ) {
			$order->update_meta_data( OrderMapper::EXPORTED_META, $format->id() );
			$order->update_meta_data( OrderMapper::EXPORTED_AT_META, (string) time() );
			$changed = true;
		}
		$note = sprintf(
			/* translators: %s: courier or template name. */
			__( 'Exported to a %s CSV file.', 'woo-courier-export' ),
			$format->label()
		);
		$status = (string) $this->settings->get( 'status_after_export' );
		if ( '' !== $status && $order->get_status() !== $status && $mark ) {
			$order->update_status( $status, $note );
			$changed = false;
		} elseif ( $this->settings->get( 'add_order_note' ) && $mark ) {
			$order->add_order_note( $note );
		}
		if ( $changed ) {
			$order->save();
		}

		do_action( 'woo_courier_export_order_exported', $order, $format->id() );
	}

	/**
	 * CSV writer from the settings.
	 */
	public function writer(): CsvWriter {
		$options = (array) apply_filters(
			'woo_courier_export_csv_options',
			array(
				'delimiter' => $this->settings->get( 'delimiter' ),
				'bom'       => $this->settings->get( 'bom' ),
			)
		);

		return ExporterFactory::writer( array_merge( $this->settings->all(), $options ) );
	}

	/**
	 * Download file name.
	 *
	 * @param string $format_id Format id.
	 */
	public function filename( string $format_id ): string {
		$name = sprintf( '%s-orders-%s.csv', $format_id, wp_date( 'Y-m-d-His' ) );

		return sanitize_file_name( (string) apply_filters( 'woo_courier_export_filename', $name, $format_id ) );
	}

	/**
	 * Human messages for skip and warning codes.
	 *
	 * @return array<string,string>
	 */
	public static function reason_labels(): array {
		return array(
			Exporter::SKIP_INVALID_PHONE   => __( 'invalid phone number', 'woo-courier-export' ),
			Exporter::SKIP_EXPORTED        => __( 'already exported', 'woo-courier-export' ),
			Exporter::SKIP_FILTERED        => __( 'excluded by a filter', 'woo-courier-export' ),
			Exporter::WARN_MISSING_ADDRESS => __( 'missing address', 'woo-courier-export' ),
			Exporter::WARN_MISSING_NAME    => __( 'missing name', 'woo-courier-export' ),
			Exporter::WARN_NO_DISTRICT     => __( 'district not found', 'woo-courier-export' ),
			Exporter::WARN_ZERO_WEIGHT     => __( 'zero weight', 'woo-courier-export' ),
		);
	}
}
