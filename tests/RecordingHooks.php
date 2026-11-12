<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests;

use Saizul\CourierExport\Support\Hooks;

/**
 * Hook dispatcher for tests: records calls and applies registered callbacks.
 */
final class RecordingHooks implements Hooks {

	/** @var array<int,array{0:string,1:string}> */
	public $calls = array();

	/** @var array<string,callable> */
	private $filters = array();

	public function on( string $name, callable $callback ): void {
		$this->filters[ $name ] = $callback;
	}

	public function filter( string $name, $value, ...$args ) {
		$this->calls[] = array( 'filter', $name );
		if ( isset( $this->filters[ $name ] ) ) {
			return ( $this->filters[ $name ] )( $value, ...$args );
		}
		return $value;
	}

	public function action( string $name, ...$args ): void {
		$this->calls[] = array( 'action', $name );
		if ( isset( $this->filters[ $name ] ) ) {
			( $this->filters[ $name ] )( ...$args );
		}
	}

	/**
	 * @return string[]
	 */
	public function names(): array {
		return array_values( array_unique( array_column( $this->calls, 1 ) ) );
	}
}
