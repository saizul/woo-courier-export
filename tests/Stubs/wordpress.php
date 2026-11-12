<?php
/**
 * Minimal WordPress function stubs for unit tests.
 *
 * Only what the WP layer touches is implemented. State lives in
 * WpStubs so tests can reset and inspect it.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

// phpcs:ignoreFile

final class WpStubs {
	/** @var array<string,array<int,array<int,callable>>> */
	public static $filters = array();
	/** @var array<string,mixed> */
	public static $options = array();
	/** @var array<string,mixed> */
	public static $transients = array();
	/** @var bool */
	public static $can = true;
	/** @var int */
	public static $user_id = 1;
	/** @var array<int,array{0:string,1:array<int,mixed>}> */
	public static $actions = array();

	public static function reset(): void {
		self::$filters    = array();
		self::$options    = array();
		self::$transients = array();
		self::$can        = true;
		self::$user_id    = 1;
		self::$actions    = array();
	}
}

final class WpDieException extends RuntimeException {
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) {
	WpStubs::$filters[ $name ][ $priority ][] = $callback;
	return true;
}

function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $name, $callback, $priority, $accepted_args );
}

function apply_filters( $name, $value, ...$args ) {
	if ( empty( WpStubs::$filters[ $name ] ) ) {
		return $value;
	}
	ksort( WpStubs::$filters[ $name ] );
	foreach ( WpStubs::$filters[ $name ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = $callback( $value, ...$args );
		}
	}
	return $value;
}

function do_action( $name, ...$args ) {
	WpStubs::$actions[] = array( $name, $args );
	if ( empty( WpStubs::$filters[ $name ] ) ) {
		return;
	}
	foreach ( WpStubs::$filters[ $name ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$callback( ...$args );
		}
	}
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return (string) $url;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, WpStubs::$options ) ? WpStubs::$options[ $name ] : $default;
}

function update_option( $name, $value ) {
	WpStubs::$options[ $name ] = $value;
	return true;
}

function get_transient( $name ) {
	return WpStubs::$transients[ $name ] ?? false;
}

function set_transient( $name, $value, $ttl = 0 ) {
	WpStubs::$transients[ $name ] = $value;
	return true;
}

function delete_transient( $name ) {
	unset( WpStubs::$transients[ $name ] );
	return true;
}

function current_user_can( $capability ) {
	return WpStubs::$can;
}

function get_current_user_id() {
	return WpStubs::$user_id;
}

function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 2 ), random_int( 0, 30 ), $length );
}

function admin_url( $path = '' ) {
	return 'https://shop.test/wp-admin/' . ltrim( $path, '/' );
}

function add_query_arg( array $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

function wp_nonce_url( $url, $action = -1 ) {
	return $url . '&_wpnonce=' . substr( md5( (string) $action ), 0, 10 );
}

function wp_die( $message = '', $title = '', $args = array() ) {
	throw new WpDieException( (string) $message );
}

function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, $timestamp ?? 1762956000 );
}

function sanitize_file_name( $name ) {
	return (string) preg_replace( '/[^A-Za-z0-9._-]+/', '-', $name );
}

function sanitize_key( $key ) {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function is_admin() {
	return true;
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}
