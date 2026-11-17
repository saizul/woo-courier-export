<?php
/**
 * Builds a configured exporter from settings.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Export;

use Saizul\CourierExport\Location\LocationMapper;
use Saizul\CourierExport\Money\CodCalculator;
use Saizul\CourierExport\Phone\PhoneNormalizer;
use Saizul\CourierExport\Settings\SettingsSchema;
use Saizul\CourierExport\Support\Hooks;
use Saizul\CourierExport\Support\MapParser;
use Saizul\CourierExport\Template\TemplateEngine;
use Saizul\CourierExport\Weight\WeightCalculator;

/**
 * Wires the pure services together from a sanitised settings array.
 */
final class ExporterFactory {

	/**
	 * Build an exporter.
	 *
	 * @param array<string,mixed> $settings Settings (missing keys use defaults).
	 * @param Hooks|null          $hooks    Hooks.
	 */
	public static function create( array $settings, ?Hooks $hooks = null ): Exporter {
		$settings = SettingsSchema::sanitize( $settings );
		$phones   = new PhoneNormalizer();
		$cod      = new CodCalculator( $settings['cod_methods'], $settings['cod_rounding'], $settings['unpaid_non_cod'] );
		$weights  = new WeightCalculator( (float) $settings['default_item_weight'], (float) $settings['minimum_weight'], 2 );
		$mapper   = new LocationMapper( MapParser::parse( $settings['district_map'] ), MapParser::parse( $settings['area_map'] ), $hooks );
		$engine   = new TemplateEngine( $phones );
		$contexts = new ContextBuilder( $phones, $cod, $weights, $mapper, $hooks );

		return new Exporter( $contexts, $engine, $hooks );
	}

	/**
	 * Export options from settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	public static function options( array $settings ): ExportOptions {
		$settings = SettingsSchema::sanitize( $settings );

		return ExportOptions::from_array(
			array(
				'invalid_phone'   => $settings['invalid_phone'],
				'duplicates'      => $settings['duplicate_orders'],
				'duplicate_match' => $settings['duplicate_match'],
				'skip_exported'   => $settings['skip_exported'],
				'store'           => array(
					'pathao_store_id'      => $settings['pathao_store_id'],
					'pathao_delivery_type' => $settings['pathao_delivery_type'],
					'pathao_item_type'     => $settings['pathao_item_type'],
					'redx_pickup_store_id' => $settings['redx_pickup_store_id'],
				),
			)
		);
	}

	/**
	 * CSV writer from settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	public static function writer( array $settings ): CsvWriter {
		$settings  = SettingsSchema::sanitize( $settings );
		$delimiter = 'tab' === $settings['delimiter'] ? "\t" : $settings['delimiter'];

		return new CsvWriter( $delimiter, "\r\n", (bool) $settings['bom'], true );
	}
}
