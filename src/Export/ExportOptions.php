<?php
/**
 * Export behaviour options.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Export;

/**
 * Options that change which orders are exported and how.
 */
final class ExportOptions {

	public const INVALID_PHONE_INCLUDE = 'include';
	public const INVALID_PHONE_SKIP    = 'skip';

	public const DUPLICATES_WARN  = 'warn';
	public const DUPLICATES_SKIP  = 'skip';
	public const DUPLICATES_ALLOW = 'allow';

	public const DUPLICATE_MATCH_PHONE         = 'phone';
	public const DUPLICATE_MATCH_PHONE_ADDRESS = 'phone_address';

	/**
	 * What to do with orders whose phone cannot be normalised.
	 *
	 * @var string
	 */
	public $invalid_phone = self::INVALID_PHONE_INCLUDE;

	/**
	 * What to do with orders that go to the same recipient as an earlier order
	 * in the same export: warn, skip or allow silently.
	 *
	 * @var string
	 */
	public $duplicates = self::DUPLICATES_WARN;

	/**
	 * How duplicate recipients are recognised: by phone number alone, or by
	 * phone number and delivery address together.
	 *
	 * @var string
	 */
	public $duplicate_match = self::DUPLICATE_MATCH_PHONE;

	/**
	 * Skip orders that have been exported before (any format).
	 *
	 * @var bool
	 */
	public $skip_exported = false;

	/**
	 * Store-level values available as {store.*} in templates
	 * (Pathao store id, RedX pickup store, delivery type...).
	 *
	 * @var array<string,string>
	 */
	public $store = array();

	/**
	 * Maximum characters in the item summary.
	 *
	 * @var int
	 */
	public $item_summary_length = 200;

	/**
	 * Build from an array.
	 *
	 * @param array<string,mixed> $data Values.
	 */
	public static function from_array( array $data ): self {
		$options = new self();
		if ( isset( $data['invalid_phone'] ) && self::INVALID_PHONE_SKIP === $data['invalid_phone'] ) {
			$options->invalid_phone = self::INVALID_PHONE_SKIP;
		}
		if ( isset( $data['duplicates'] ) && in_array( $data['duplicates'], array( self::DUPLICATES_SKIP, self::DUPLICATES_ALLOW ), true ) ) {
			$options->duplicates = (string) $data['duplicates'];
		}
		if ( isset( $data['duplicate_match'] ) && self::DUPLICATE_MATCH_PHONE_ADDRESS === $data['duplicate_match'] ) {
			$options->duplicate_match = self::DUPLICATE_MATCH_PHONE_ADDRESS;
		}
		$options->skip_exported = ! empty( $data['skip_exported'] );
		if ( isset( $data['store'] ) && is_array( $data['store'] ) ) {
			$options->store = array_map( 'strval', $data['store'] );
		}
		if ( isset( $data['item_summary_length'] ) ) {
			$options->item_summary_length = max( 0, (int) $data['item_summary_length'] );
		}

		return $options;
	}
}
