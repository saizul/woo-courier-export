<?php
/**
 * Parser for "from => to" mapping text.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Support;

/**
 * Parses textarea settings such as:
 *
 *     # Woo value => courier value
 *     BD-13 => Dhaka City
 *     Savar => Savar (Dhaka)
 *
 * Blank lines and lines starting with # are ignored. "=>", "=" and a tab are
 * accepted as separators; the first separator on a line wins.
 */
final class MapParser {

	/**
	 * Parse mapping text.
	 *
	 * @param string $text Text.
	 * @return array<string,string>
	 */
	public static function parse( string $text ): array {
		$map = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$parts = preg_split( '/\s*(?:=>|\t|=)\s*/', $line, 2 );
			if ( ! is_array( $parts ) || 2 !== count( $parts ) ) {
				continue;
			}
			$from = trim( $parts[0] );
			$to   = trim( $parts[1] );
			if ( '' !== $from ) {
				$map[ $from ] = $to;
			}
		}

		return $map;
	}

	/**
	 * Turn a map back into text.
	 *
	 * @param array<string,string> $map Map.
	 */
	public static function dump( array $map ): string {
		$lines = array();
		foreach ( $map as $from => $to ) {
			$lines[] = $from . ' => ' . $to;
		}

		return implode( "\n", $lines );
	}
}
