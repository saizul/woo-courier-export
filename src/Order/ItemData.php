<?php
/**
 * Line item snapshot.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Order;

/**
 * One order line, independent of WooCommerce classes.
 */
final class ItemData {

	/**
	 * Product name as shown on the order.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * SKU, may be empty.
	 *
	 * @var string
	 */
	public $sku = '';

	/**
	 * Quantity ordered.
	 *
	 * @var int
	 */
	public $quantity = 1;

	/**
	 * Weight of one unit in kilograms, null when unknown.
	 *
	 * @var float|null
	 */
	public $weight_kg = null;

	/**
	 * Line total including tax.
	 *
	 * @var float
	 */
	public $total = 0.0;

	/**
	 * Virtual or downloadable items are not shipped.
	 *
	 * @var bool
	 */
	public $is_virtual = false;

	/**
	 * Build from an associative array (unknown keys are ignored).
	 *
	 * @param array<string,mixed> $data Values.
	 */
	public static function from_array( array $data ): self {
		$item             = new self();
		$item->name       = isset( $data['name'] ) ? (string) $data['name'] : '';
		$item->sku        = isset( $data['sku'] ) ? (string) $data['sku'] : '';
		$item->quantity   = isset( $data['quantity'] ) ? (int) $data['quantity'] : 1;
		$item->weight_kg  = isset( $data['weight_kg'] ) && '' !== $data['weight_kg'] ? (float) $data['weight_kg'] : null;
		$item->total      = isset( $data['total'] ) ? (float) $data['total'] : 0.0;
		$item->is_virtual = ! empty( $data['is_virtual'] );

		return $item;
	}

	/**
	 * Values for the template context.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'name'       => $this->name,
			'sku'        => $this->sku,
			'quantity'   => $this->quantity,
			'weight_kg'  => $this->weight_kg,
			'total'      => $this->total,
			'is_virtual' => $this->is_virtual,
		);
	}
}
