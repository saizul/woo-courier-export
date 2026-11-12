<?php
/**
 * Settings access.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\WP;

use Saizul\CourierExport\Settings\SettingsSchema;

/**
 * Reads the plugin option through {@see SettingsSchema}.
 */
final class Settings {

	/**
	 * Cached sanitised settings.
	 *
	 * @var array<string,mixed>|null
	 */
	private $cache = null;

	/**
	 * All settings with defaults applied.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$raw         = get_option( SettingsSchema::OPTION, array() );
			$this->cache = SettingsSchema::sanitize( is_array( $raw ) ? $raw : array() );
		}

		return $this->cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( string $key ) {
		$all = $this->all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Forget the cache (after saving).
	 */
	public function flush(): void {
		$this->cache = null;
	}
}
