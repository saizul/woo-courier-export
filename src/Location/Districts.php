<?php
/**
 * Bangladesh district reference data.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Location;

/**
 * The 64 districts of Bangladesh keyed by ISO 3166-2:BD code (the codes
 * WooCommerce uses for Bangladeshi states), plus older or alternative
 * spellings customers and couriers still use.
 */
final class Districts {

	/**
	 * ISO 3166-2:BD district code => current official English name.
	 *
	 * @var array<string,string>
	 */
	public const BY_CODE = array(
		'BD-01' => 'Bandarban',
		'BD-02' => 'Barguna',
		'BD-03' => 'Bogura',
		'BD-04' => 'Brahmanbaria',
		'BD-05' => 'Bagerhat',
		'BD-06' => 'Barishal',
		'BD-07' => 'Bhola',
		'BD-08' => 'Cumilla',
		'BD-09' => 'Chandpur',
		'BD-10' => 'Chattogram',
		'BD-11' => "Cox's Bazar",
		'BD-12' => 'Chuadanga',
		'BD-13' => 'Dhaka',
		'BD-14' => 'Dinajpur',
		'BD-15' => 'Faridpur',
		'BD-16' => 'Feni',
		'BD-17' => 'Gopalganj',
		'BD-18' => 'Gazipur',
		'BD-19' => 'Gaibandha',
		'BD-20' => 'Habiganj',
		'BD-21' => 'Jamalpur',
		'BD-22' => 'Jashore',
		'BD-23' => 'Jhenaidah',
		'BD-24' => 'Joypurhat',
		'BD-25' => 'Jhalokati',
		'BD-26' => 'Kishoreganj',
		'BD-27' => 'Khulna',
		'BD-28' => 'Kurigram',
		'BD-29' => 'Khagrachhari',
		'BD-30' => 'Kushtia',
		'BD-31' => 'Lakshmipur',
		'BD-32' => 'Lalmonirhat',
		'BD-33' => 'Manikganj',
		'BD-34' => 'Mymensingh',
		'BD-35' => 'Munshiganj',
		'BD-36' => 'Madaripur',
		'BD-37' => 'Magura',
		'BD-38' => 'Moulvibazar',
		'BD-39' => 'Meherpur',
		'BD-40' => 'Narayanganj',
		'BD-41' => 'Netrokona',
		'BD-42' => 'Narsingdi',
		'BD-43' => 'Narail',
		'BD-44' => 'Natore',
		'BD-45' => 'Chapainawabganj',
		'BD-46' => 'Nilphamari',
		'BD-47' => 'Noakhali',
		'BD-48' => 'Naogaon',
		'BD-49' => 'Pabna',
		'BD-50' => 'Pirojpur',
		'BD-51' => 'Patuakhali',
		'BD-52' => 'Panchagarh',
		'BD-53' => 'Rajbari',
		'BD-54' => 'Rajshahi',
		'BD-55' => 'Rangpur',
		'BD-56' => 'Rangamati',
		'BD-57' => 'Sherpur',
		'BD-58' => 'Satkhira',
		'BD-59' => 'Sirajganj',
		'BD-60' => 'Sylhet',
		'BD-61' => 'Sunamganj',
		'BD-62' => 'Shariatpur',
		'BD-63' => 'Tangail',
		'BD-64' => 'Thakurgaon',
	);

	/**
	 * Alternative spelling (lower case) => official name.
	 *
	 * @var array<string,string>
	 */
	public const ALIASES = array(
		'bogra'            => 'Bogura',
		'barisal'          => 'Barishal',
		'comilla'          => 'Cumilla',
		'chittagong'       => 'Chattogram',
		'ctg'              => 'Chattogram',
		'chattagram'       => 'Chattogram',
		'coxs bazar'       => "Cox's Bazar",
		'cox bazar'        => "Cox's Bazar",
		'coxsbazar'        => "Cox's Bazar",
		'jessore'          => 'Jashore',
		'jhenaidaha'       => 'Jhenaidah',
		'jhalakathi'       => 'Jhalokati',
		'jhalokathi'       => 'Jhalokati',
		'jaipurhat'        => 'Joypurhat',
		'khagrachari'      => 'Khagrachhari',
		'lakshmipur'       => 'Lakshmipur',
		'laxmipur'         => 'Lakshmipur',
		'maulvibazar'      => 'Moulvibazar',
		'moulvi bazar'     => 'Moulvibazar',
		'netrakona'        => 'Netrokona',
		'nawabganj'        => 'Chapainawabganj',
		'chapai nawabganj' => 'Chapainawabganj',
		'narshingdi'       => 'Narsingdi',
		'rangamati'        => 'Rangamati',
		'rangamati hill'   => 'Rangamati',
		'brahmanbaria'     => 'Brahmanbaria',
		'b baria'          => 'Brahmanbaria',
		'brahamanbaria'    => 'Brahmanbaria',
		'gaibanda'         => 'Gaibandha',
		'hobiganj'         => 'Habiganj',
		'kishorganj'       => 'Kishoreganj',
		'munshigonj'       => 'Munshiganj',
		'narayangonj'      => 'Narayanganj',
		'sirajgonj'        => 'Sirajganj',
		'gazipur sadar'    => 'Gazipur',
		'dhaka city'       => 'Dhaka',
	);

	/**
	 * Official name for an ISO code, or null.
	 *
	 * @param string $code Code such as "BD-13" (also accepts "13" and "bd13").
	 */
	public static function name_for_code( string $code ): ?string {
		$code = strtoupper( trim( $code ) );
		if ( preg_match( '/^(?:BD-?)?(\d{1,2})$/', $code, $m ) ) {
			$code = sprintf( 'BD-%02d', (int) $m[1] );
		}

		return self::BY_CODE[ $code ] ?? null;
	}

	/**
	 * Official name for a free-text district, or null when not recognised.
	 *
	 * @param string $text District name as typed ("Chittagong", "dhaka ", "Cox's Bazar").
	 */
	public static function canonical_name( string $text ): ?string {
		$key = self::key( $text );
		if ( '' === $key ) {
			return null;
		}
		if ( isset( self::ALIASES[ $key ] ) ) {
			return self::ALIASES[ $key ];
		}
		foreach ( self::BY_CODE as $name ) {
			if ( self::key( $name ) === $key ) {
				return $name;
			}
		}
		$stripped = (string) preg_replace( '/\s+(district|zila|zilla|sadar)$/', '', $key );
		if ( $stripped !== $key ) {
			return self::canonical_name( $stripped );
		}

		return null;
	}

	/**
	 * Comparison key: lower case, apostrophes and punctuation removed.
	 *
	 * @param string $text Text.
	 */
	private static function key( string $text ): string {
		$text = strtolower( trim( $text ) );
		$text = str_replace( array( "'", '’', '`' ), '', $text );
		$text = (string) preg_replace( '/[^a-z0-9]+/', ' ', $text );

		return trim( $text );
	}
}
