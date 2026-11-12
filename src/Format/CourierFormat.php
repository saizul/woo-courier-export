<?php
/**
 * Courier CSV format contract.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Format;

use Saizul\CourierExport\Template\Column;

/**
 * A named list of columns that produces one courier's bulk-upload file.
 */
interface CourierFormat {

	/**
	 * Stable id used in settings, URLs and WP-CLI (lower case, a-z0-9_-).
	 */
	public function id(): string;

	/**
	 * Human label.
	 */
	public function label(): string;

	/**
	 * Columns in output order.
	 *
	 * @return Column[]
	 */
	public function columns(): array;

	/**
	 * Header row.
	 *
	 * @return string[]
	 */
	public function headers(): array;
}
