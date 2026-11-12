<?php
/**
 * Result of a phone normalisation.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Phone;

/**
 * Immutable outcome of {@see PhoneNormalizer::normalize()}.
 */
final class PhoneResult {

	public const REASON_EMPTY            = 'empty';
	public const REASON_NOT_A_NUMBER     = 'not_a_number';
	public const REASON_TOO_SHORT        = 'too_short';
	public const REASON_TOO_LONG         = 'too_long';
	public const REASON_NOT_MOBILE       = 'not_mobile';
	public const REASON_UNKNOWN_OPERATOR = 'unknown_operator';
	public const REASON_PLACEHOLDER      = 'placeholder';

	/**
	 * Input as received.
	 *
	 * @var string
	 */
	private $raw;

	/**
	 * Normalised 01XXXXXXXXX number, or null.
	 *
	 * @var string|null
	 */
	private $local;

	/**
	 * Machine-readable reason when invalid.
	 *
	 * @var string|null
	 */
	private $reason;

	/**
	 * Constructor.
	 *
	 * @param string      $raw    Input as received.
	 * @param string|null $local  Normalised number.
	 * @param string|null $reason Reason code when invalid.
	 */
	private function __construct( string $raw, ?string $local, ?string $reason ) {
		$this->raw    = $raw;
		$this->local  = $local;
		$this->reason = $reason;
	}

	/**
	 * Build a valid result.
	 *
	 * @param string $raw   Input.
	 * @param string $local Normalised number.
	 */
	public static function valid( string $raw, string $local ): self {
		return new self( $raw, $local, null );
	}

	/**
	 * Build an invalid result.
	 *
	 * @param string $raw    Input.
	 * @param string $reason One of the REASON_* constants.
	 */
	public static function invalid( string $raw, string $reason ): self {
		return new self( $raw, null, $reason );
	}

	/**
	 * Whether the number is a usable Bangladeshi mobile number.
	 */
	public function is_valid(): bool {
		return null !== $this->local;
	}

	/**
	 * The number as received.
	 */
	public function raw(): string {
		return $this->raw;
	}

	/**
	 * 01XXXXXXXXX, or null when invalid.
	 */
	public function local(): ?string {
		return $this->local;
	}

	/**
	 * +8801XXXXXXXXX, or null when invalid.
	 */
	public function international(): ?string {
		return null === $this->local ? null : '+88' . $this->local;
	}

	/**
	 * Reason code when invalid, null otherwise.
	 */
	public function reason(): ?string {
		return $this->reason;
	}

	/**
	 * Operator prefix such as "017", or null.
	 */
	public function operator_prefix(): ?string {
		return null === $this->local ? null : substr( $this->local, 0, 3 );
	}
}
