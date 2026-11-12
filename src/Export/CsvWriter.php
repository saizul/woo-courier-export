<?php
/**
 * CSV encoder.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Export;

use InvalidArgumentException;

/**
 * RFC 4180 CSV encoder with spreadsheet-safety features.
 *
 * - Configurable delimiter and line ending (fputcsv cannot change the line
 *   ending before PHP 8.1).
 * - Optional UTF-8 byte order mark so Excel shows Bangla text correctly.
 * - Formula-injection protection: text cells starting with =, +, -, @, tab or
 *   carriage return are prefixed with an apostrophe. Plain numbers such as
 *   "-5" or "+8801711000000" are left alone.
 */
final class CsvWriter {

	public const BOM = "\xEF\xBB\xBF";

	/**
	 * Field delimiter.
	 *
	 * @var string
	 */
	private $delimiter;

	/**
	 * Line ending.
	 *
	 * @var string
	 */
	private $eol;

	/**
	 * Whether to write a byte order mark.
	 *
	 * @var bool
	 */
	private $bom;

	/**
	 * Whether to neutralise formulas.
	 *
	 * @var bool
	 */
	private $protect;

	/**
	 * Constructor.
	 *
	 * @param string $delimiter One character: comma, semicolon, tab or pipe.
	 * @param string $eol       "\r\n" or "\n".
	 * @param bool   $bom       Write a UTF-8 BOM.
	 * @param bool   $protect   Neutralise spreadsheet formulas.
	 *
	 * @throws InvalidArgumentException When the delimiter or line ending is invalid.
	 */
	public function __construct( string $delimiter = ',', string $eol = "\r\n", bool $bom = true, bool $protect = true ) {
		if ( 1 !== strlen( $delimiter ) || in_array( $delimiter, array( '"', "\r", "\n" ), true ) ) {
			throw new InvalidArgumentException( 'Invalid CSV delimiter.' );
		}
		if ( ! in_array( $eol, array( "\r\n", "\n" ), true ) ) {
			throw new InvalidArgumentException( 'Invalid line ending.' );
		}
		$this->delimiter = $delimiter;
		$this->eol       = $eol;
		$this->bom       = $bom;
		$this->protect   = $protect;
	}

	/**
	 * Encode a whole file.
	 *
	 * @param string[]            $headers Header row (skipped when empty).
	 * @param array<int,string[]> $rows    Rows.
	 */
	public function to_string( array $headers, array $rows ): string {
		$out = $this->bom ? self::BOM : '';
		if ( array() !== $headers ) {
			$out .= $this->line( $headers, false );
		}
		foreach ( $rows as $row ) {
			$out .= $this->line( $row, $this->protect );
		}

		return $out;
	}

	/**
	 * Write a whole file to a stream.
	 *
	 * @param resource            $stream  Writable stream.
	 * @param string[]            $headers Header row.
	 * @param array<int,string[]> $rows    Rows.
	 *
	 * @throws InvalidArgumentException When the stream is not a resource.
	 */
	public function write( $stream, array $headers, array $rows ): void {
		if ( ! is_resource( $stream ) ) {
			throw new InvalidArgumentException( 'Expected a stream resource.' );
		}
		if ( $this->bom ) {
			fwrite( $stream, self::BOM ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download.
		}
		if ( array() !== $headers ) {
			fwrite( $stream, $this->line( $headers, false ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download.
		}
		foreach ( $rows as $row ) {
			fwrite( $stream, $this->line( $row, $this->protect ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download.
		}
	}

	/**
	 * Encode one line.
	 *
	 * @param array<int,mixed> $fields  Fields.
	 * @param bool             $protect Neutralise formulas.
	 */
	public function line( array $fields, bool $protect = true ): string {
		$cells = array();
		foreach ( $fields as $field ) {
			$cells[] = $this->cell( (string) $field, $protect );
		}

		return implode( $this->delimiter, $cells ) . $this->eol;
	}

	/**
	 * Encode one cell.
	 *
	 * @param string $value   Value.
	 * @param bool   $protect Neutralise formulas.
	 */
	public function cell( string $value, bool $protect = true ): string {
		if ( $protect && '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $value ) ) {
			$value = "'" . $value;
		}
		$needs_quotes = '' !== $value && (
			false !== strpbrk( $value, $this->delimiter . "\"\r\n" )
			|| ' ' === $value[0]
			|| ' ' === substr( $value, -1 )
		);
		if ( $needs_quotes ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
