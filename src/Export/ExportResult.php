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
		$this->warnings[ $order_id ][] = $code;
	}

	/**
	 * Number of exported rows.
	 */
	public function count(): int {
		return count( $this->rows );
	}
}
