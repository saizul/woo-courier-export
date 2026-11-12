<?php
/**
 * District and area mapping.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Location;

use Saizul\CourierExport\Order\OrderData;
use Saizul\CourierExport\Support\Hooks;
use Saizul\CourierExport\Support\NullHooks;

/**
 * Resolves the district and area values written to the CSV.
 *
 * Order of precedence for the district:
 *
 * 1. The store's own map ("from => to" lines in the settings), matched
 *    case-insensitively against the state code, state name and city.
 * 2. The state code (BD-13 => Dhaka).
 * 3. The state name or city recognised as a district (aliases included).
 * 4. The raw state name, then the city.
 *
 * The area defaults to the city field after the area map is applied. Both
 * values then pass through the `woo_courier_export_district` and
 * `woo_courier_export_area` filters so stores can map to a courier's own
 * zone names or ids.
 */
final class LocationMapper {

	/**
	 * Store district map (lower-case key => value).
	 *
	 * @var array<string,string>
	 */
	private $district_map;

	/**
	 * Store area map (lower-case key => value).
	 *
	 * @var array<string,string>
	 */
	private $area_map;

	/**
	 * Hook dispatcher.
	 *
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * Constructor.
	 *
	 * @param array<string,string> $district_map Store district map.
	 * @param array<string,string> $area_map     Store area map.
	 * @param Hooks|null           $hooks        Hook dispatcher.
	 */
	public function __construct( array $district_map = array(), array $area_map = array(), ?Hooks $hooks = null ) {
		$this->district_map = self::lower_keys( $district_map );
		$this->area_map     = self::lower_keys( $area_map );
		$this->hooks        = $hooks ?? new NullHooks();
	}

	/**
	 * District for an order.
	 *
	 * @param OrderData $order  Order.
	 * @param string    $format Format id the value is for.
	 */
	public function district( OrderData $order, string $format = '' ): string {
		$value = $this->resolve_district( $order );

		return (string) $this->hooks->filter( 'woo_courier_export_district', $value, $order, $format );
	}

	/**
	 * Area (thana/upazila/zone) for an order.
	 *
	 * @param OrderData $order  Order.
	 * @param string    $format Format id the value is for.
	 */
	public function area( OrderData $order, string $format = '' ): string {
		$city  = trim( $order->city );
		$value = $this->area_map[ strtolower( $city ) ] ?? $city;

		return (string) $this->hooks->filter( 'woo_courier_export_area', $value, $order, $format );
	}

	/**
	 * District before filters.
	 *
	 * @param OrderData $order Order.
	 */
	private function resolve_district( OrderData $order ): string {
		foreach ( array( $order->state, $order->state_name, $order->city ) as $candidate ) {
			$key = strtolower( trim( $candidate ) );
			if ( '' !== $key && isset( $this->district_map[ $key ] ) ) {
				return $this->district_map[ $key ];
			}
		}

		if ( 'BD' === strtoupper( $order->country ) || '' === $order->country ) {
			$by_code = '' !== $order->state ? Districts::name_for_code( $order->state ) : null;
			if ( null !== $by_code ) {
				return $by_code;
			}
			foreach ( array( $order->state_name, $order->state, $order->city ) as $candidate ) {
				$name = Districts::canonical_name( $candidate );
				if ( null !== $name ) {
					return $name;
				}
			}
		}

		$fallback = trim( '' !== trim( $order->state_name ) ? $order->state_name : $order->state );

		return '' !== $fallback ? $fallback : trim( $order->city );
	}

	/**
	 * Lower-case the keys of a map.
	 *
	 * @param array<string,string> $map Map.
	 * @return array<string,string>
	 */
	private static function lower_keys( array $map ): array {
		$out = array();
		foreach ( $map as $key => $value ) {
			$out[ strtolower( trim( (string) $key ) ) ] = (string) $value;
		}

		return $out;
	}
}
