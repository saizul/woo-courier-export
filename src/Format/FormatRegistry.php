<?php
/**
 * Registry of available formats.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Format;

use InvalidArgumentException;

/**
 * Holds the formats offered in the bulk actions and WP-CLI.
 */
final class FormatRegistry {

	/**
	 * Formats by id.
	 *
	 * @var array<string,CourierFormat>
	 */
	private $formats = array();

	/**
	 * Constructor.
	 *
	 * @param CourierFormat[] $formats Formats.
	 */
	public function __construct( array $formats = array() ) {
		foreach ( $formats as $format ) {
			$this->add( $format );
		}
	}

	/**
	 * Add or replace a format.
	 *
	 * @param CourierFormat $format Format.
	 */
	public function add( CourierFormat $format ): void {
		$this->formats[ $format->id() ] = $format;
	}

	/**
	 * Remove a format.
	 *
	 * @param string $id Format id.
	 */
	public function remove( string $id ): void {
		unset( $this->formats[ $id ] );
	}

	/**
	 * Whether a format exists.
	 *
	 * @param string $id Format id.
	 */
	public function has( string $id ): bool {
		return isset( $this->formats[ $id ] );
	}

	/**
	 * Get a format.
	 *
	 * @param string $id Format id.
	 *
	 * @throws InvalidArgumentException When the format does not exist.
	 */
	public function get( string $id ): CourierFormat {
		if ( ! isset( $this->formats[ $id ] ) ) {
			throw new InvalidArgumentException( 'Unknown courier format: ' . $id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}

		return $this->formats[ $id ];
	}

	/**
	 * All formats in insertion order.
	 *
	 * @return array<string,CourierFormat>
	 */
	public function all(): array {
		return $this->formats;
	}

	/**
	 * Id => label.
	 *
	 * @return array<string,string>
	 */
	public function labels(): array {
		$labels = array();
		foreach ( $this->formats as $id => $format ) {
			$labels[ $id ] = $format->label();
		}

		return $labels;
	}
}
