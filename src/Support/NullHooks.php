<?php
/**
 * No-op hook dispatcher.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Support;

/**
 * Returns values unchanged and ignores actions.
 */
final class NullHooks implements Hooks {

	/**
	 * Return the value unchanged.
	 *
	 * @param string $name  Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Ignored.
	 * @return mixed
	 */
	public function filter( string $name, $value, ...$args ) {
		return $value;
	}

	/**
	 * Do nothing.
	 *
	 * @param string $name Hook name.
	 * @param mixed  ...$args Ignored.
	 */
	public function action( string $name, ...$args ): void {
	}
}
