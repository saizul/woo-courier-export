<?php
/**
 * WordPress hook dispatcher.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP;

use Saizul\CourierExport\Support\Hooks;

/**
 * Forwards the core's hook calls to apply_filters() and do_action().
 */
final class WpHooks implements Hooks {

	/**
	 * Filter a value.
	 *
	 * @param string $name  Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	public function filter( string $name, $value, ...$args ) {
		return apply_filters( $name, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Every core hook name starts with woo_courier_export_.
	}

	/**
	 * Fire an action.
	 *
	 * @param string $name Hook name.
	 * @param mixed  ...$args Arguments.
	 */
	public function action( string $name, ...$args ): void {
		do_action( $name, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Every core hook name starts with woo_courier_export_.
	}
}
