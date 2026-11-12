<?php
/**
 * Parcel weight calculation.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Weight;

use InvalidArgumentException;
use Saizul\CourierExport\Order\OrderData;

/**
 * Sums product weights into a parcel weight in kilograms.
 *
 * Items without a weight use the configured default per unit. The total is
 * raised to the configured minimum (couriers usually bill at least 0.5 kg or
 * 1 kg) and rounded up to the configured precision.
 */
final class WeightCalculator {

	/**
	 * Kilograms per unit for the units WooCommerce supports.
	 *
	 * @var array<string,float>
	 */
	public const TO_KG = array(
		'kg'  => 1.0,
		'g'   => 0.001,
		'lbs' => 0.45359237,
		'lb'  => 0.45359237,
		'oz'  => 0.028349523125,
	);

	/**
	 * Weight used per unit when a product has none, in kg.
	 *
	 * @var float
	 */
	private $default_item_kg;

	/**
	 * Minimum parcel weight in kg.
	 *
	 * @var float
	 */
	private $minimum_kg;

	/**
	 * Decimals kept when rounding up.
	 *
	 * @var int
	 */
	private $precision;

	/**
	 * Constructor.
	 *
	 * @param float $default_item_kg Weight per unit when a product has none.
	 * @param float $minimum_kg      Minimum parcel weight.
	 * @param int   $precision       Decimals kept (rounded up).
	 */
	public function __construct( float $default_item_kg = 0.0, float $minimum_kg = 0.0, int $precision = 2 ) {
		$this->default_item_kg = max( 0.0, $default_item_kg );
		$this->minimum_kg      = max( 0.0, $minimum_kg );
		$this->precision       = max( 0, min( 6, $precision ) );
	}

	/**
	 * Convert a weight to kilograms.
	 *
	 * @param float  $value Weight.
	 * @param string $unit  Unit: kg, g, lbs, lb or oz.
	 *
	 * @throws InvalidArgumentException When the unit is unknown.
	 */
	public static function to_kg( float $value, string $unit ): float {
		$unit = strtolower( trim( $unit ) );
		if ( ! isset( self::TO_KG[ $unit ] ) ) {
			throw new InvalidArgumentException( 'Unknown weight unit: ' . $unit ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}

		return $value * self::TO_KG[ $unit ];
	}

	/**
	 * Parcel weight for an order in kilograms.
	 *
	 * @param OrderData $order Order snapshot. Item weights are expected in kg.
	 */
	public function calculate( OrderData $order ): float {
		$total = 0.0;
		foreach ( $order->items as $item ) {
			if ( $item->is_virtual ) {
				continue;
			}
			$unit   = null !== $item->weight_kg && $item->weight_kg > 0 ? $item->weight_kg : $this->default_item_kg;
			$total += $unit * max( 0, $item->quantity );
		}

		return $this->finish( $total );
	}

	/**
	 * Apply the minimum and round up to the precision.
	 *
	 * @param float $kg Raw weight in kg.
	 */
	public function finish( float $kg ): float {
		$kg     = max( $kg, $this->minimum_kg );
		$factor = 10 ** $this->precision;

		// Round away float noise before ceil() so 0.30000000004 stays 0.3.
		return ceil( round( $kg * $factor, 6 ) ) / $factor;
	}
}
