<?php
/**
 * Bangladeshi mobile number normalisation.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Phone;

/**
 * Turns the many ways customers type a Bangladeshi mobile number into the
 * 11-digit local form couriers expect: 01XXXXXXXXX.
 *
 * Accepted input includes +8801..., 8801..., 008801..., 01..., 1... (the
 * leading zero dropped), Bangla digits (০-৯), and separators such as spaces,
 * dots, dashes and parentheses.
 */
final class PhoneNormalizer {

	/**
	 * Mobile operator prefixes in use (013-019). 012 has never been assigned.
	 *
	 * @var string[]
	 */
	public const OPERATOR_PREFIXES = array( '013', '014', '015', '016', '017', '018', '019' );

	/**
	 * Bangla (and Arabic-Indic) digits mapped to ASCII.
	 *
	 * @var array<string,string>
	 */
	private const DIGIT_MAP = array(
		'০' => '0',
		'১' => '1',
		'২' => '2',
		'৩' => '3',
		'৪' => '4',
		'৫' => '5',
		'৬' => '6',
		'৭' => '7',
		'৮' => '8',
		'৯' => '9',
		'٠' => '0',
		'١' => '1',
		'٢' => '2',
		'٣' => '3',
		'٤' => '4',
		'٥' => '5',
		'٦' => '6',
		'٧' => '7',
		'٨' => '8',
		'٩' => '9',
	);

	/**
	 * Normalise a phone number and explain the outcome.
	 *
	 * @param string|null $raw Phone number as typed by the customer.
	 */
	public function normalize( ?string $raw ): PhoneResult {
		$raw = (string) $raw;
		if ( '' === trim( $raw ) ) {
			return PhoneResult::invalid( $raw, PhoneResult::REASON_EMPTY );
		}

		$value = strtr( $raw, self::DIGIT_MAP );

		// Several numbers in one field ("017..., 018...") are common; keep the first.
		$parts = preg_split( '/\s*[,;\/|]\s*|\s+or\s+/i', trim( $value ) );
		$value = is_array( $parts ) && '' !== $parts[0] ? $parts[0] : $value;

		if ( preg_match( '/[a-z]/i', (string) preg_replace( '/^\s*(tel|phone|mobile|mob)\s*[:.]?/i', '', $value ) ) ) {
			return PhoneResult::invalid( $raw, PhoneResult::REASON_NOT_A_NUMBER );
		}

		$digits = (string) preg_replace( '/\D+/', '', $value );

		if ( 0 === strpos( $digits, '00880' ) ) {
			$digits = substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '880' ) ) {
			$digits = substr( $digits, 3 );
		}

		// "880 0171..." and "+880 (0)171..." keep a redundant trunk zero.
		if ( 12 === strlen( $digits ) && 0 === strpos( $digits, '001' ) ) {
			$digits = substr( $digits, 1 );
		}

		if ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = '0' . $digits;
		}

		if ( 11 !== strlen( $digits ) ) {
			return PhoneResult::invalid(
				$raw,
				strlen( $digits ) < 11 ? PhoneResult::REASON_TOO_SHORT : PhoneResult::REASON_TOO_LONG
			);
		}

		if ( 0 !== strpos( $digits, '01' ) ) {
			return PhoneResult::invalid( $raw, PhoneResult::REASON_NOT_MOBILE );
		}

		if ( ! in_array( substr( $digits, 0, 3 ), self::OPERATOR_PREFIXES, true ) ) {
			return PhoneResult::invalid( $raw, PhoneResult::REASON_UNKNOWN_OPERATOR );
		}

		if ( preg_match( '/^01\d(\d)\1{7}$/', $digits ) ) {
			return PhoneResult::invalid( $raw, PhoneResult::REASON_PLACEHOLDER );
		}

		return PhoneResult::valid( $raw, $digits );
	}

	/**
	 * Shortcut returning the normalised number, or null when it is invalid.
	 *
	 * @param string|null $raw Phone number as typed by the customer.
	 */
	public function to_local( ?string $raw ): ?string {
		$result = $this->normalize( $raw );

		return $result->is_valid() ? $result->local() : null;
	}

	/**
	 * Shortcut returning the E.164 form (+8801XXXXXXXXX), or null.
	 *
	 * @param string|null $raw Phone number as typed by the customer.
	 */
	public function to_international( ?string $raw ): ?string {
		$result = $this->normalize( $raw );

		return $result->is_valid() ? $result->international() : null;
	}
}
