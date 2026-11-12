<?php
/**
 * Parser for column definitions written in the settings screen.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Template;

/**
 * Parses one column per line in the form "Header = template":
 *
 *     # Generic courier template
 *     Invoice      = {order.number}
 *     Name         = {recipient.name|truncate:100}
 *     Phone        = {recipient.phone}
 *     COD          = {cod_amount|int}
 *     Instructions = Call before delivery. {order.note}
 *
 * Lines starting with # are comments. Header and template are trimmed. A
 * header with no "=" is an error, so typos do not silently drop columns.
 */
final class ColumnParser {

	/**
	 * Parse definition text.
	 *
	 * @param string $text Definition text.
	 * @return Column[]
	 *
	 * @throws TemplateException When a line is malformed or a header repeats.
	 */
	public static function parse( string $text ): array {
		$columns = array();
		$seen    = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $number => $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$pos = strpos( $line, '=' );
			if ( false === $pos ) {
				throw new TemplateException( sprintf( 'Line %d: expected "Header = template".', $number + 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where displayed.
			}
			$header   = trim( substr( $line, 0, $pos ) );
			$template = trim( substr( $line, $pos + 1 ) );
			if ( '' === $header ) {
				throw new TemplateException( sprintf( 'Line %d: the header is empty.', $number + 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where displayed.
			}
			$key = strtolower( $header );
			if ( isset( $seen[ $key ] ) ) {
				throw new TemplateException( sprintf( 'Line %d: duplicate header "%s".', $number + 1, $header ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where displayed.
			}
			$seen[ $key ] = true;
			$columns[]    = new Column( $header, $template );
		}
		if ( array() === $columns ) {
			throw new TemplateException( 'The template has no columns.' );
		}

		return $columns;
	}

	/**
	 * Turn columns back into definition text, aligning the "=" signs.
	 *
	 * @param Column[] $columns Columns.
	 */
	public static function dump( array $columns ): string {
		$width = 0;
		foreach ( $columns as $column ) {
			$width = max( $width, strlen( $column->header ) );
		}
		$lines = array();
		foreach ( $columns as $column ) {
			$lines[] = str_pad( $column->header, $width ) . ' = ' . $column->template;
		}

		return implode( "\n", $lines );
	}
}
