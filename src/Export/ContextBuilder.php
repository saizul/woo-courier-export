<?php
/**
 * Builds template contexts from orders.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Export;

use Saizul\CourierExport\Location\LocationMapper;
use Saizul\CourierExport\Money\CodCalculator;
use Saizul\CourierExport\Order\ItemData;
use Saizul\CourierExport\Order\OrderData;
use Saizul\CourierExport\Phone\PhoneNormalizer;
use Saizul\CourierExport\Phone\PhoneResult;
use Saizul\CourierExport\Support\Hooks;
use Saizul\CourierExport\Support\NullHooks;
use Saizul\CourierExport\Weight\WeightCalculator;

/**
 * Turns an {@see OrderData} into the array that column templates read.
 *
 * Available values (see README for the full table):
 *
 * - order.*: id, number, date, status, total, subtotal, shipping_total, paid,
 *   payment_method, payment_method_title, currency, note, email, item_count,
 *   item_summary, items (list of name/sku/quantity/weight_kg/total)
 * - recipient.*: name, phone, phone_raw, phone_intl, phone_valid, alt_phone,
 *   address, address_with_area, address_1, address_2, city, area, district,
 *   area_or_district, state, postcode, country
 * - cod_amount, weight_kg, weight_g, store.*, meta.*
 */
final class ContextBuilder {

	/**
	 * Phone normaliser.
	 *
	 * @var PhoneNormalizer
	 */
	private $phones;

	/**
	 * COD calculator.
	 *
	 * @var CodCalculator
	 */
	private $cod;

	/**
	 * Weight calculator.
	 *
	 * @var WeightCalculator
	 */
	private $weights;

	/**
	 * Location mapper.
	 *
	 * @var LocationMapper
	 */
	private $locations;

	/**
	 * Hooks.
	 *
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * Constructor.
	 *
	 * @param PhoneNormalizer  $phones    Phone normaliser.
	 * @param CodCalculator    $cod       COD calculator.
	 * @param WeightCalculator $weights   Weight calculator.
	 * @param LocationMapper   $locations Location mapper.
	 * @param Hooks|null       $hooks     Hooks.
	 */
	public function __construct( PhoneNormalizer $phones, CodCalculator $cod, WeightCalculator $weights, LocationMapper $locations, ?Hooks $hooks = null ) {
		$this->phones    = $phones;
		$this->cod       = $cod;
		$this->weights   = $weights;
		$this->locations = $locations;
		$this->hooks     = $hooks ?? new NullHooks();
	}

	/**
	 * Phone result for an order (after the phone filter).
	 *
	 * @param OrderData $order Order.
	 */
	public function phone( OrderData $order ): PhoneResult {
		$result   = $this->phones->normalize( $order->phone );
		$filtered = $this->hooks->filter( 'woo_courier_export_phone', $result, $order );

		return $filtered instanceof PhoneResult ? $filtered : $result;
	}

	/**
	 * Build the context.
	 *
	 * @param OrderData     $order   Order.
	 * @param string        $format  Format id.
	 * @param ExportOptions $options Options.
	 * @return array<string,mixed>
	 */
	public function build( OrderData $order, string $format, ExportOptions $options ): array {
		$phone     = $this->phone( $order );
		$alt       = $this->phones->normalize( $order->alt_phone );
		$cod       = (float) $this->hooks->filter( 'woo_courier_export_cod_amount', $this->cod->calculate( $order ), $order );
		$weight    = (float) $this->hooks->filter( 'woo_courier_export_weight', $this->weights->calculate( $order ), $order );
		$district  = $this->locations->district( $order, $format );
		$area      = $this->locations->area( $order, $format );
		$address   = $order->full_address();
		$with_area = $address;
		foreach ( array( $area, $district ) as $part ) {
			if ( '' !== $part && false === stripos( $with_area, $part ) ) {
				$with_area .= ( '' === $with_area ? '' : ', ' ) . $part;
			}
		}
		$subtotal = 0.0;
		foreach ( $order->items as $item ) {
			$subtotal += $item->total;
		}

		$context = array(
			'order'      => array(
				'id'                   => $order->id,
				'number'               => $order->number,
				'date'                 => $order->date_created,
				'status'               => $order->status,
				'total'                => $order->total,
				'subtotal'             => $subtotal,
				'shipping_total'       => $order->shipping_total,
				'paid'                 => $order->paid_amount,
				'is_paid'              => $order->is_paid,
				'payment_method'       => $order->payment_method,
				'payment_method_title' => $order->payment_method_title,
				'currency'             => $order->currency,
				'note'                 => $order->customer_note,
				'email'                => $order->email,
				'item_count'           => $order->item_count(),
				'item_summary'         => $order->item_summary( $options->item_summary_length ),
				'items'                => array_map(
					static function ( ItemData $item ): array {
						return $item->to_array();
					},
					$order->items
				),
			),
			'recipient'  => array(
				'name'              => trim( $order->name ),
				'phone'             => $phone->is_valid() ? $phone->local() : trim( $order->phone ),
				'phone_raw'         => $order->phone,
				'phone_intl'        => $phone->is_valid() ? $phone->international() : '',
				'phone_valid'       => $phone->is_valid(),
				'alt_phone'         => $alt->is_valid() && $alt->local() !== $phone->local() ? $alt->local() : '',
				'address'           => $address,
				'address_with_area' => $with_area,
				'address_1'         => $order->address_1,
				'address_2'         => $order->address_2,
				'city'              => $order->city,
				'area'              => $area,
				'district'          => $district,
				'area_or_district'  => '' !== $area ? $area : $district,
				'state'             => $order->state,
				'postcode'          => $order->postcode,
				'country'           => $order->country,
			),
			'cod_amount' => $cod,
			'weight_kg'  => $weight,
			'weight_g'   => (int) ceil( round( $weight * 1000, 3 ) ),
			'store'      => $options->store,
			'meta'       => $order->meta,
		);

		return $this->hooks->filter( 'woo_courier_export_context', $context, $order, $format );
	}
}
