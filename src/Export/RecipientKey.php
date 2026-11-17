<?php
/**
 * Keys that identify a recipient for duplicate detection.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Export;

/**
 * Builds the comparison key used to spot several orders going to the same
 * recipient in one export (repeat clicks, test orders, fake orders).
 *
 * The phone part is the normalised 01XXXXXXXXX number when it is valid, or
 * the digits that were typed otherwise. The optional address part is the
 * delivery address in lower case with punctuation and extra spaces removed,
 * so "House 12, Road 5" and "house 12 road-5" match.
 */
final class RecipientKey {

	/**
	 * Fewest digits an invalid phone needs to be compared at all.
	 */
	private const MIN_RAW_DIGITS = 6;

	/**
	 * Key for a template context, or '' when the order cannot be compared.
	 *
	 * @param array<string,mixed> $context Template context from {@see ContextBuilder}.
	 * @param string              $mode   One of the ExportOptions::DUPLICATE_MATCH_* values.
	 */
	public static function from_context( array $context, string $mode = ExportOptions::DUPLICATE_MATCH_PHONE ): string {
		$recipient = isset( $context['recipient'] ) && is_array( $context['recipient'] ) ? $context['recipient'] : array();
		$phone     = self::phone( $recipient );
		if ( '' === $phone ) {
			return '';
		}
		if ( ExportOptions::DUPLICATE_MATCH_PHONE_ADDRESS !== $mode ) {
			return $phone;
		}
		$address = self::address( (string) ( $recipient['address_with_area'] ?? $recipient['address'] ?? '' ) );

		return $phone . '|' . $address;
	}

	/**
	 * Phone part of the key.
	 *
	 * @param array<string,mixed> $recipient Recipient values.
	 */
	public static function phone( array $recipient ): string {
		if ( ! empty( $recipient['phone_valid'] ) ) {
			return (string) ( $recipient['phone'] ?? '' );
		}
		$digits = (string) preg_replace( '/\D+/', '', (string) ( $recipient['phone_raw'] ?? $recipient['phone'] ?? '' ) );

		return strlen( $digits ) >= self::MIN_RAW_DIGITS ? $digits : '';
	}

	/**
	 * Address part of the key: lower case, letters and digits separated by
	 * single spaces.
	 *
	 * @param string $address Address.
	 */
	public static function address( string $address ): string {
		$address = function_exists( 'mb_strtolower' ) ? mb_strtolower( $address, 'UTF-8' ) : strtolower( $address );
		$address = (string) preg_replace( '/[^\p{L}\p{M}\p{N}]+/u', ' ', $address );

		return trim( $address );
	}
}
