<?php
/**
 * CSV column definition.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Template;

/**
 * A header and the template that produces its value.
 */
final class Column {

	/**
	 * Header text.
	 *
	 * @var string
	 */
	public $header;

	/**
	 * Value template, for example "{recipient.phone}".
	 *
	 * @var string
	 */
	public $template;

	/**
	 * Constructor.
	 *
	 * @param string $header   Header text.
	 * @param string $template Value template.
	 */
	public function __construct( string $header, string $template ) {
		$this->header   = $header;
		$this->template = $template;
	}
}
