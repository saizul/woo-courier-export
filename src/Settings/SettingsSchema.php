<?php
/**
 * Settings defaults and sanitisation.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Settings;

use Saizul\CourierExport\Export\ExportOptions;
use Saizul\CourierExport\Format\BuiltInFormats;
use Saizul\CourierExport\Money\CodCalculator;
use Saizul\CourierExport\Template\ColumnParser;
use Saizul\CourierExport\Template\TemplateEngine;
use Saizul\CourierExport\Template\TemplateException;

/**
 * The single source of truth for option keys, defaults and allowed values.
 *
 * Kept free of WordPress functions so it can be unit tested; the settings
 * screen calls {@see SettingsSchema::sanitize()} before saving.
 */
final class SettingsSchema {

	public const OPTION = 'woo_courier_export_settings';

	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'default_format'       => BuiltInFormats::STEADFAST,
			'enabled_formats'      => array( BuiltInFormats::STEADFAST, BuiltInFormats::PATHAO, BuiltInFormats::REDX, BuiltInFormats::GENERIC ),
			'cod_methods'          => array( 'cod' ),
			'unpaid_non_cod'       => CodCalculator::UNPAID_COLLECT,
			'cod_rounding'         => CodCalculator::ROUND_ROUND,
			'paid_meta_key'        => '',
			'default_item_weight'  => 0.5,
			'minimum_weight'       => 0.5,
			'invalid_phone'        => ExportOptions::INVALID_PHONE_INCLUDE,
			'skip_exported'        => false,
			'mark_exported'        => true,
			'add_order_note'       => true,
			'status_after_export'  => '',
			'delimiter'            => ',',
			'bom'                  => true,
			'pathao_store_id'      => '',
			'pathao_delivery_type' => '48',
			'pathao_item_type'     => '2',
			'redx_pickup_store_id' => '',
			'district_map'         => '',
			'area_map'             => '',
			'template_steadfast'   => '',
			'template_pathao'      => '',
			'template_redx'        => '',
			'template_generic'     => '',
			'delete_on_uninstall'  => false,
		);
	}

	/**
	 * Sanitise raw settings; unknown keys are dropped, invalid values fall back
	 * to defaults. Invalid templates are dropped and reported in $errors.
	 *
	 * @param array<string,mixed> $raw    Raw values.
	 * @param string[]            $errors Receives template error messages.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw, array &$errors = array() ): array {
		$defaults = self::defaults();
		$out      = $defaults;

		$format = self::text( $raw['default_format'] ?? '' );
		if ( preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $format ) ) {
			$out['default_format'] = $format;
		}
		if ( isset( $raw['enabled_formats'] ) ) {
			$enabled = array();
			foreach ( (array) $raw['enabled_formats'] as $id ) {
				$id = self::text( $id );
				if ( preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $id ) ) {
					$enabled[] = $id;
				}
			}
			$out['enabled_formats'] = array_values( array_unique( $enabled ) );
		}

		if ( isset( $raw['cod_methods'] ) ) {
			$methods = array();
			foreach ( (array) $raw['cod_methods'] as $method ) {
				$method = self::text( $method );
				if ( preg_match( '/^[A-Za-z0-9_-]+$/', $method ) ) {
					$methods[] = $method;
				}
			}
			$out['cod_methods'] = array() === $methods ? $defaults['cod_methods'] : array_values( array_unique( $methods ) );
		}

		$out['unpaid_non_cod'] = self::choice( $raw, 'unpaid_non_cod', array( CodCalculator::UNPAID_COLLECT, CodCalculator::UNPAID_ZERO ) );
		$out['cod_rounding']   = self::choice( $raw, 'cod_rounding', array( CodCalculator::ROUND_ROUND, CodCalculator::ROUND_CEIL, CodCalculator::ROUND_FLOOR, CodCalculator::ROUND_NONE ) );
		$out['invalid_phone']  = self::choice( $raw, 'invalid_phone', array( ExportOptions::INVALID_PHONE_INCLUDE, ExportOptions::INVALID_PHONE_SKIP ) );
		$out['delimiter']      = self::choice( $raw, 'delimiter', array( ',', ';', 'tab', '|' ) );

		$meta_key             = self::text( $raw['paid_meta_key'] ?? '' );
		$out['paid_meta_key'] = preg_match( '/^[A-Za-z0-9_\-]{0,191}$/', $meta_key ) ? $meta_key : '';

		foreach ( array( 'default_item_weight', 'minimum_weight' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_numeric( $raw[ $key ] ) ) {
				$out[ $key ] = max( 0.0, min( 1000.0, round( (float) $raw[ $key ], 3 ) ) );
			}
		}

		foreach ( array( 'skip_exported', 'mark_exported', 'add_order_note', 'bom', 'delete_on_uninstall' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = self::flag( $raw[ $key ] );
			}
		}

		$status                     = self::text( $raw['status_after_export'] ?? '' );
		$out['status_after_export'] = preg_match( '/^(wc-)?[a-z0-9_-]{1,20}$/', $status ) ? (string) preg_replace( '/^wc-/', '', $status ) : '';

		foreach ( array( 'pathao_store_id', 'pathao_delivery_type', 'pathao_item_type', 'redx_pickup_store_id' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$value       = self::text( $raw[ $key ] );
				$out[ $key ] = preg_match( '/^[A-Za-z0-9_-]{0,64}$/', $value ) ? $value : $defaults[ $key ];
			}
		}

		foreach ( array( 'district_map', 'area_map' ) as $key ) {
			$out[ $key ] = self::textarea( $raw[ $key ] ?? '' );
		}

		$engine = new TemplateEngine();
		foreach ( array_keys( BuiltInFormats::definitions() ) as $id ) {
			$key  = 'template_' . $id;
			$text = self::textarea( $raw[ $key ] ?? '' );
			if ( '' === trim( $text ) ) {
				$out[ $key ] = '';
				continue;
			}
			$problem = self::template_error( $text, $engine );
			if ( null === $problem ) {
				$out[ $key ] = $text;
			} else {
				$errors[]    = $id . ': ' . $problem;
				$out[ $key ] = '';
			}
		}

		return $out;
	}

	/**
	 * Convert sanitised settings to the stored form.
	 *
	 * The WooCommerce settings API renders checkboxes as checked only when the
	 * stored value is "yes", so booleans are stored as "yes"/"no" and turned back
	 * into booleans by {@see SettingsSchema::sanitize()} when read.
	 *
	 * @param array<string,mixed> $settings Sanitised settings.
	 * @return array<string,mixed>
	 */
	public static function to_storage( array $settings ): array {
		foreach ( $settings as $key => $value ) {
			if ( is_bool( $value ) ) {
				$settings[ $key ] = $value ? 'yes' : 'no';
			}
		}

		return $settings;
	}

	/**
	 * First problem in a column definition, or null when it is valid.
	 *
	 * @param string              $text   Definition text.
	 * @param TemplateEngine|null $engine Engine (for custom filters).
	 */
	public static function template_error( string $text, ?TemplateEngine $engine = null ): ?string {
		$engine = $engine ?? new TemplateEngine();
		try {
			foreach ( ColumnParser::parse( $text ) as $column ) {
				$engine->compile( $column->template );
			}
		} catch ( TemplateException $e ) {
			return $e->getMessage();
		}

		return null;
	}

	/**
	 * Single-line text without control characters.
	 *
	 * @param mixed $value Value.
	 */
	private static function text( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', '', (string) $value ) );
	}

	/**
	 * Multi-line text without control characters other than newlines and tabs.
	 *
	 * @param mixed $value Value.
	 */
	private static function textarea( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
		$value = (string) preg_replace( '/[\x00-\x08\x0B-\x1F\x7F]+/', '', $value );

		return trim( substr( $value, 0, 20000 ) );
	}

	/**
	 * Boolean from checkbox-like values.
	 *
	 * @param mixed $value Value.
	 */
	private static function flag( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( self::text( $value ) ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Allowed value or the default.
	 *
	 * @param array<string,mixed> $raw     Raw values.
	 * @param string              $key     Key.
	 * @param string[]            $allowed Allowed values (first is not necessarily the default).
	 */
	private static function choice( array $raw, string $key, array $allowed ): string {
		$value = isset( $raw[ $key ] ) ? self::text( $raw[ $key ] ) : '';

		return in_array( $value, $allowed, true ) ? $value : (string) self::defaults()[ $key ];
	}
}
