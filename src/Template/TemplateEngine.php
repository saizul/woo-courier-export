<?php
/**
 * Small, safe template language for CSV cell values.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Template;

use DateTimeImmutable;
use Exception;
use Saizul\CourierExport\Phone\PhoneNormalizer;

/**
 * Renders templates such as "Order #{order.number}" or
 * "{recipient.name|upper|truncate:60}" against a context array.
 *
 * Syntax:
 *
 * - `{path}` looks up a dotted path in the context (`recipient.phone`,
 *   `order.items.0.name`). Missing values render as an empty string.
 * - `{path|filter|filter:arg1:arg2}` pipes the value through filters.
 *   Arguments containing `:` or `|` can be double-quoted.
 * - `{{` and `}}` produce literal braces.
 *
 * There is no code execution, no function calls and no access outside the
 * context array. Unknown filters are rejected when the template is compiled,
 * so mistakes surface in the settings screen instead of in a courier upload.
 */
final class TemplateEngine {

	/**
	 * Registered filters.
	 *
	 * @var array<string,callable>
	 */
	private $filters = array();

	/**
	 * Compiled template cache.
	 *
	 * @var array<string,array<int,string|array{path:string,filters:array<int,array{0:string,1:string[]}>}>>
	 */
	private $cache = array();

	/**
	 * Phone normaliser used by the phone filters.
	 *
	 * @var PhoneNormalizer
	 */
	private $phones;

	/**
	 * Constructor.
	 *
	 * @param PhoneNormalizer|null $phones Phone normaliser.
	 */
	public function __construct( ?PhoneNormalizer $phones = null ) {
		$this->phones = $phones ?? new PhoneNormalizer();
		$this->register_default_filters();
	}

	/**
	 * Register or replace a filter.
	 *
	 * The callable receives the current value followed by the string arguments.
	 *
	 * @param string   $name     Filter name (letters, digits and underscores).
	 * @param callable $callback Callback.
	 *
	 * @throws TemplateException When the name is invalid.
	 */
	public function add_filter( string $name, callable $callback ): void {
		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $name ) ) {
			throw new TemplateException( 'Invalid filter name: ' . $name ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}
		$this->filters[ $name ] = $callback;
		$this->cache            = array();
	}

	/**
	 * Names of the registered filters.
	 *
	 * @return string[]
	 */
	public function filter_names(): array {
		$names = array_keys( $this->filters );
		sort( $names );

		return $names;
	}

	/**
	 * Render a template.
	 *
	 * @param string              $template Template.
	 * @param array<string,mixed> $context  Values.
	 *
	 * @throws TemplateException When the template is invalid.
	 */
	public function render( string $template, array $context ): string {
		$out = '';
		foreach ( $this->compile( $template ) as $segment ) {
			if ( is_string( $segment ) ) {
				$out .= $segment;
				continue;
			}
			$value = self::lookup( $context, $segment['path'] );
			foreach ( $segment['filters'] as $filter ) {
				$value = call_user_func_array( $this->filters[ $filter[0] ], array_merge( array( $value ), $filter[1] ) );
			}
			$out .= self::stringify( $value );
		}

		return $out;
	}

	/**
	 * Check a template and return the problems found (empty when valid).
	 *
	 * @param string              $template Template.
	 * @param array<string,mixed> $context  Optional sample context to check paths against.
	 * @return string[]
	 */
	public function validate( string $template, array $context = array() ): array {
		try {
			$segments = $this->compile( $template );
		} catch ( TemplateException $e ) {
			return array( $e->getMessage() );
		}
		$errors = array();
		if ( array() !== $context ) {
			foreach ( $segments as $segment ) {
				if ( is_array( $segment ) && ! self::has_path( $context, $segment['path'] ) ) {
					$errors[] = sprintf( 'Unknown value "%s".', $segment['path'] );
				}
			}
		}

		return $errors;
	}

	/**
	 * Paths used by a template.
	 *
	 * @param string $template Template.
	 * @return string[]
	 */
	public function paths( string $template ): array {
		$paths = array();
		foreach ( $this->compile( $template ) as $segment ) {
			if ( is_array( $segment ) ) {
				$paths[] = $segment['path'];
			}
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Compile a template into literal strings and expressions.
	 *
	 * @param string $template Template.
	 * @return array<int,string|array{path:string,filters:array<int,array{0:string,1:string[]}>}>
	 *
	 * @throws TemplateException When the template is invalid.
	 */
	public function compile( string $template ): array {
		if ( isset( $this->cache[ $template ] ) ) {
			return $this->cache[ $template ];
		}
		$segments = array();
		$literal  = '';
		$length   = strlen( $template );
		$i        = 0;
		while ( $i < $length ) {
			$char = $template[ $i ];
			$next = $i + 1 < $length ? $template[ $i + 1 ] : '';
			if ( '{' === $char && '{' === $next ) {
				$literal .= '{';
				$i       += 2;
				continue;
			}
			if ( '}' === $char && '}' === $next ) {
				$literal .= '}';
				$i       += 2;
				continue;
			}
			if ( '}' === $char ) {
				throw new TemplateException( sprintf( 'Unexpected "}" at position %d; write "}}" for a literal brace.', $i + 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where displayed.
			}
			if ( '{' !== $char ) {
				$literal .= $char;
				++$i;
				continue;
			}
			$end = $this->find_close( $template, $i + 1 );
			if ( null === $end ) {
				throw new TemplateException( sprintf( 'Unclosed "{" at position %d.', $i + 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where displayed.
			}
			if ( '' !== $literal ) {
				$segments[] = $literal;
				$literal    = '';
			}
			$segments[] = $this->parse_expression( substr( $template, $i + 1, $end - $i - 1 ) );
			$i          = $end + 1;
		}
		if ( '' !== $literal ) {
			$segments[] = $literal;
		}
		$this->cache[ $template ] = $segments;

		return $segments;
	}

	/**
	 * Find the closing brace of an expression, skipping quoted arguments.
	 *
	 * @param string $template Template.
	 * @param int    $start    Offset after the opening brace.
	 */
	private function find_close( string $template, int $start ): ?int {
		$in_quotes = false;
		$length    = strlen( $template );
		for ( $i = $start; $i < $length; $i++ ) {
			$char = $template[ $i ];
			if ( '\\' === $char && $in_quotes ) {
				++$i;
				continue;
			}
			if ( '"' === $char ) {
				$in_quotes = ! $in_quotes;
			} elseif ( '}' === $char && ! $in_quotes ) {
				return $i;
			} elseif ( '{' === $char && ! $in_quotes ) {
				return null;
			}
		}

		return null;
	}

	/**
	 * Parse "path|filter:arg|filter".
	 *
	 * @param string $source Expression source without braces.
	 * @return array{path:string,filters:array<int,array{0:string,1:string[]}>}
	 *
	 * @throws TemplateException When the expression is invalid.
	 */
	private function parse_expression( string $source ): array {
		$parts = self::split( $source, '|' );
		$path  = trim( (string) array_shift( $parts ) );
		if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z0-9_-]+)*$/', $path ) ) {
			throw new TemplateException( sprintf( 'Invalid value name "%s".', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where displayed.
		}
		$filters = array();
		foreach ( $parts as $part ) {
			$args = self::split( trim( $part ), ':' );
			$name = strtolower( trim( (string) array_shift( $args ) ) );
			if ( ! isset( $this->filters[ $name ] ) ) {
				throw new TemplateException( sprintf( 'Unknown filter "%s".', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where displayed.
			}
			$filters[] = array( $name, array_map( array( self::class, 'unquote' ), $args ) );
		}

		return array(
			'path'    => $path,
			'filters' => $filters,
		);
	}

	/**
	 * Split on a separator outside double quotes.
	 *
	 * @param string $text      Text.
	 * @param string $separator Single-character separator.
	 * @return string[]
	 */
	private static function split( string $text, string $separator ): array {
		$out       = array();
		$buffer    = '';
		$in_quotes = false;
		$length    = strlen( $text );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $text[ $i ];
			if ( '\\' === $char && $in_quotes && $i + 1 < $length ) {
				$buffer .= $char . $text[ $i + 1 ];
				++$i;
				continue;
			}
			if ( '"' === $char ) {
				$in_quotes = ! $in_quotes;
			}
			if ( $char === $separator && ! $in_quotes ) {
				$out[]  = $buffer;
				$buffer = '';
				continue;
			}
			$buffer .= $char;
		}
		$out[] = $buffer;

		return $out;
	}

	/**
	 * Remove surrounding quotes and unescape \" and \\.
	 *
	 * @param string $value Argument.
	 */
	private static function unquote( string $value ): string {
		$value = trim( $value );
		if ( strlen( $value ) >= 2 && '"' === $value[0] && '"' === substr( $value, -1 ) ) {
			return str_replace( array( '\\"', '\\\\' ), array( '"', '\\' ), substr( $value, 1, -1 ) );
		}

		return $value;
	}

	/**
	 * Look up a dotted path.
	 *
	 * @param array<string,mixed> $context Context.
	 * @param string              $path    Dotted path.
	 * @return mixed
	 */
	private static function lookup( array $context, string $path ) {
		$value = $context;
		foreach ( explode( '.', $path ) as $key ) {
			if ( is_array( $value ) && array_key_exists( $key, $value ) ) {
				$value = $value[ $key ];
			} else {
				return null;
			}
		}

		return $value;
	}

	/**
	 * Whether a dotted path exists.
	 *
	 * @param array<string,mixed> $context Context.
	 * @param string              $path    Dotted path.
	 */
	private static function has_path( array $context, string $path ): bool {
		$value = $context;
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return false;
			}
			$value = $value[ $key ];
		}

		return true;
	}

	/**
	 * Convert a value to a cell string.
	 *
	 * @param mixed $value Value.
	 */
	public static function stringify( $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_float( $value ) ) {
			$text = rtrim( rtrim( number_format( $value, 6, '.', '' ), '0' ), '.' );
			return '-0' === $text ? '0' : $text;
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) || null === $item ) {
					$parts[] = self::stringify( $item );
				} elseif ( is_array( $item ) && isset( $item['name'] ) ) {
					$parts[] = self::stringify( $item['name'] );
				}
			}
			return implode( ', ', $parts );
		}
		if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Built-in filters.
	 */
	private function register_default_filters(): void {
		$s      = array( self::class, 'stringify' );
		$phones = $this->phones;

		$this->filters = array(
			'upper'      => static function ( $v ) use ( $s ) {
				return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $s( $v ) ) : strtoupper( $s( $v ) );
			},
			'lower'      => static function ( $v ) use ( $s ) {
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s( $v ) ) : strtolower( $s( $v ) );
			},
			'title'      => static function ( $v ) use ( $s ) {
				return ucwords( strtolower( $s( $v ) ) );
			},
			'trim'       => static function ( $v ) use ( $s ) {
				return trim( (string) preg_replace( '/\s+/u', ' ', $s( $v ) ) );
			},
			'truncate'   => static function ( $v, $length = '100', $ellipsis = '' ) use ( $s ) {
				$text   = $s( $v );
				$length = max( 0, (int) $length );
				if ( mb_strlen( $text ) <= $length ) {
					return $text;
				}
				return mb_substr( $text, 0, max( 0, $length - mb_strlen( $ellipsis ) ) ) . $ellipsis;
			},
			'number'     => static function ( $v, $decimals = '2' ) use ( $s ) {
				$text = $s( $v );
				return '' === $text ? '' : number_format( (float) $text, max( 0, (int) $decimals ), '.', '' );
			},
			'int'        => static function ( $v ) use ( $s ) {
				$text = $s( $v );
				return '' === $text ? '' : (string) (int) round( (float) $text );
			},
			'default'    => static function ( $v, $fallback = '' ) use ( $s ) {
				return '' === trim( $s( $v ) ) ? $fallback : $v;
			},
			'replace'    => static function ( $v, $search = '', $replace = '' ) use ( $s ) {
				return '' === $search ? $s( $v ) : str_replace( $search, $replace, $s( $v ) );
			},
			'digits'     => static function ( $v ) use ( $s ) {
				return (string) preg_replace( '/\D+/', '', $s( $v ) );
			},
			'phone'      => static function ( $v ) use ( $s, $phones ) {
				$local = $phones->to_local( $s( $v ) );
				return null === $local ? $s( $v ) : $local;
			},
			'phone_intl' => static function ( $v ) use ( $s, $phones ) {
				$intl = $phones->to_international( $s( $v ) );
				return null === $intl ? $s( $v ) : $intl;
			},
			'date'       => static function ( $v, $format = 'Y-m-d' ) use ( $s ) {
				$text = $s( $v );
				if ( '' === $text ) {
					return '';
				}
				try {
					return ( new DateTimeImmutable( $text ) )->format( $format );
				} catch ( Exception $e ) {
					return $text;
				}
			},
			'join'       => static function ( $v, $separator = ', ', $key = 'name' ) use ( $s ) {
				if ( ! is_array( $v ) ) {
					return $s( $v );
				}
				$parts = array();
				foreach ( $v as $item ) {
					$parts[] = is_array( $item ) ? $s( $item[ $key ] ?? '' ) : $s( $item );
				}
				return implode( $separator, $parts );
			},
			'first'      => static function ( $v ) {
				return is_array( $v ) && array() !== $v ? reset( $v ) : $v;
			},
			'grams'      => static function ( $v ) use ( $s ) {
				$text = $s( $v );
				return '' === $text ? '' : (string) (int) ceil( round( (float) $text * 1000, 3 ) );
			},
			'prefix'     => static function ( $v, $prefix = '' ) use ( $s ) {
				return '' === $s( $v ) ? '' : $prefix . $s( $v );
			},
			'suffix'     => static function ( $v, $suffix = '' ) use ( $s ) {
				return '' === $s( $v ) ? '' : $s( $v ) . $suffix;
			},
			'single'     => static function ( $v ) use ( $s ) {
				return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', $s( $v ) ) );
			},
		);
	}
}
