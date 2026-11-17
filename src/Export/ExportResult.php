<?php
/**
 * Result of an export run.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Export;

/**
 * Rows produced, orders skipped and warnings raised.
 */
final class ExportResult {

	/**
	 * Format id.
	 *
	 * @var string
	 */
	public $format = '';

	/**
	 * Header row.
	 *
	 * @var string[]
	 */
	public $headers = array();

	/**
	 * Data rows.
	 *
	 * @var array<int,string[]>
	 */
	public $rows = array();

	/**
	 * Ids of exported orders, in row order.
	 *
	 * @var int[]
	 */
	public $exported_ids = array();

	/**
	 * Skipped order id => reason code.
	 *
	 * @var array<int,string>
	 */
	public $skipped = array();

	/**
	 * Order id => list of warning codes.
	 *
	 * @var array<int,string[]>
	 */
	public $warnings = array();

	/**
	 * Duplicate order id => id of the first order in this export that goes to
	 * the same recipient.
	 *
	 * @var array<int,int>
	 */
	public $duplicates = array();

	/**
	 * Sum of the COD amounts exported.
	 *
	 * @var float
	 */
	public $cod_total = 0.0;

	/**
	 * Record a warning.
	 *
	 * @param int    $order_id Order id.
	 * @param string $code     Warning code.
	 */
	public function warn( int $order_id, string $code ): void {
		if ( ! in_array( $code, $this->warnings[ $order_id ] ?? array(), true ) ) {
			$this->warnings[ $order_id ][] = $code;
		}
	}

	/**
	 * Groups of order ids that go to the same recipient, each starting with the
	 * first order of the group.
	 *
	 * @return array<int,int[]>
	 */
	public function duplicate_groups(): array {
		$groups = array();
		foreach ( $this->duplicates as $id => $first ) {
			if ( ! isset( $groups[ $first ] ) ) {
				$groups[ $first ] = array( $first );
			}
			$groups[ $first ][] = $id;
		}

		return array_values( $groups );
	}

	/**
	 * Number of exported rows.
	 */
	public function count(): int {
		return count( $this->rows );
	}
}
