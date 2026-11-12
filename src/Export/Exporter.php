<?php
/**
 * Export pipeline.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Export;

use Saizul\CourierExport\Format\CourierFormat;
use Saizul\CourierExport\Order\OrderData;
use Saizul\CourierExport\Support\Hooks;
use Saizul\CourierExport\Support\NullHooks;
use Saizul\CourierExport\Template\TemplateEngine;

/**
 * Converts orders into CSV rows for one courier format.
 */
final class Exporter {

	public const SKIP_INVALID_PHONE = 'invalid_phone';
	public const SKIP_EXPORTED      = 'already_exported';
	public const SKIP_FILTERED      = 'filtered';

	public const WARN_INVALID_PHONE   = 'invalid_phone';
	public const WARN_MISSING_ADDRESS = 'missing_address';
	public const WARN_MISSING_NAME    = 'missing_name';
	public const WARN_NO_DISTRICT     = 'no_district';
	public const WARN_ZERO_WEIGHT     = 'zero_weight';

	/**
	 * Context builder.
	 *
	 * @var ContextBuilder
	 */
	private $contexts;

	/**
	 * Template engine.
	 *
	 * @var TemplateEngine
	 */
	private $engine;

	/**
	 * Hooks.
	 *
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * Constructor.
	 *
	 * @param ContextBuilder $contexts Context builder.
	 * @param TemplateEngine $engine   Template engine.
	 * @param Hooks|null     $hooks    Hooks.
	 */
	public function __construct( ContextBuilder $contexts, TemplateEngine $engine, ?Hooks $hooks = null ) {
		$this->contexts = $contexts;
		$this->engine   = $engine;
		$this->hooks    = $hooks ?? new NullHooks();
	}

	/**
	 * Export orders.
	 *
	 * @param iterable           $orders  Orders (OrderData instances).
	 * @param CourierFormat      $format  Format.
	 * @param ExportOptions|null $options Options.
	 */
	public function export( iterable $orders, CourierFormat $format, ?ExportOptions $options = null ): ExportResult {
		$options         = $options ?? new ExportOptions();
		$result          = new ExportResult();
		$result->format  = $format->id();
		$result->headers = $format->headers();

		foreach ( $orders as $order ) {
			if ( $options->skip_exported && ! empty( $order->meta['_woo_courier_export_exported'] ) ) {
				$result->skipped[ $order->id ] = self::SKIP_EXPORTED;
				continue;
			}
			if ( ! $this->hooks->filter( 'woo_courier_export_include_order', true, $order, $format->id() ) ) {
				$result->skipped[ $order->id ] = self::SKIP_FILTERED;
				continue;
			}

			$context = $this->contexts->build( $order, $format->id(), $options );

			if ( empty( $context['recipient']['phone_valid'] ) ) {
				if ( ExportOptions::INVALID_PHONE_SKIP === $options->invalid_phone ) {
					$result->skipped[ $order->id ] = self::SKIP_INVALID_PHONE;
					continue;
				}
				$result->warn( $order->id, self::WARN_INVALID_PHONE );
			}
			if ( '' === trim( (string) $context['recipient']['name'] ) ) {
				$result->warn( $order->id, self::WARN_MISSING_NAME );
			}
			if ( '' === trim( (string) $context['recipient']['address'] ) ) {
				$result->warn( $order->id, self::WARN_MISSING_ADDRESS );
			}
			if ( '' === trim( (string) $context['recipient']['district'] ) ) {
				$result->warn( $order->id, self::WARN_NO_DISTRICT );
			}
			if ( $context['weight_kg'] <= 0 && $order->item_count() > 0 ) {
				$result->warn( $order->id, self::WARN_ZERO_WEIGHT );
			}

			$row = array();
			foreach ( $format->columns() as $column ) {
				$row[] = $this->engine->render( $column->template, $context );
			}
			$row = array_values( array_map( 'strval', (array) $this->hooks->filter( 'woo_courier_export_row', $row, $order, $format->id(), $context ) ) );

			$result->rows[]         = $row;
			$result->exported_ids[] = $order->id;
			$result->cod_total     += (float) $context['cod_amount'];
		}

		return $result;
	}
}
