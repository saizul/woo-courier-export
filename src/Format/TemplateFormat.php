<?php
/**
 * Format defined by a list of column templates.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Format;

use InvalidArgumentException;
use Saizul\CourierExport\Template\Column;
use Saizul\CourierExport\Template\ColumnParser;

/**
 * The only format implementation needed: built-in couriers, the generic
 * template and formats added by other plugins are all column lists.
 */
final class TemplateFormat implements CourierFormat {

	/**
	 * Format id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Columns.
	 *
	 * @var Column[]
	 */
	private $columns;

	/**
	 * Constructor.
	 *
	 * @param string   $id      Format id.
	 * @param string   $label   Label.
	 * @param Column[] $columns Columns.
	 *
	 * @throws InvalidArgumentException When the id or columns are invalid.
	 */
	public function __construct( string $id, string $label, array $columns ) {
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $id ) ) {
			throw new InvalidArgumentException( 'Invalid format id: ' . $id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}
		if ( array() === $columns ) {
			throw new InvalidArgumentException( 'A format needs at least one column.' );
		}
		foreach ( $columns as $column ) {
			if ( ! $column instanceof Column ) {
				throw new InvalidArgumentException( 'Columns must be Column instances.' );
			}
		}
		$this->id      = $id;
		$this->label   = $label;
		$this->columns = array_values( $columns );
	}

	/**
	 * Build a format from "Header = template" lines.
	 *
	 * @param string $id         Format id.
	 * @param string $label      Label.
	 * @param string $definition Column definition text.
	 */
	public static function from_definition( string $id, string $label, string $definition ): self {
		return new self( $id, $label, ColumnParser::parse( $definition ) );
	}

	/**
	 * Copy of this format with different columns.
	 *
	 * @param Column[] $columns Columns.
	 */
	public function with_columns( array $columns ): self {
		return new self( $this->id, $this->label, $columns );
	}

	/**
	 * Format id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Columns.
	 *
	 * @return Column[]
	 */
	public function columns(): array {
		return $this->columns;
	}

	/**
	 * Header row.
	 *
	 * @return string[]
	 */
	public function headers(): array {
		return array_map(
			static function ( Column $column ): string {
				return $column->header;
			},
			$this->columns
		);
	}
}
