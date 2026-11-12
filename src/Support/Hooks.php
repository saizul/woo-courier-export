<?php
/**
 * Hook dispatcher interface.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Support;

/**
 * Lets the WordPress-free core call filters and actions.
 *
 * In WordPress the implementation forwards to apply_filters() and do_action();
 * in tests a recording or no-op implementation is used.
 */
interface Hooks {

	/**
	 * Filter a value.
	 *
	 * @param string $name  Hook name.
	 * @param mixed  $value Value to filter.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	public function filter( string $name, $value, ...$args );

	/**
	 * Fire an action.
	 *
	 * @param string $name Hook name.
	 * @param mixed  ...$args Arguments.
	 */
	public function action( string $name, ...$args ): void;
}
