<?php
/**
 * Cash-on-delivery amount calculation.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Money;

use InvalidArgumentException;
use Saizul\CourierExport\Order\OrderData;

/**
 * Works out how much the courier must collect from the customer.
 *
 * The rule is "order total minus what has already been paid":
 *
 * - Orders paid with a cash-on-delivery method collect the total minus any
 *   advance (for example a delivery charge paid by bKash before dispatch).
 * - Orders paid in full online collect nothing.
 * - Unpaid orders with another method (bank transfer awaiting payment) follow
 *   the "unpaid_non_cod" policy: collect the balance, or collect nothing.
 *
 * The result is never negative and is rounded according to the policy, because
 * most couriers only accept whole taka.
 */
final class CodCalculator {

	public const ROUND_NONE  = 'none';
	public const ROUND_ROUND = 'round';
	public const ROUND_CEIL  = 'ceil';
	public const ROUND_FLOOR = 'floor';

	public const UNPAID_COLLECT = 'collect';
	public const UNPAID_ZERO    = 'zero';

	/**
	 * Payment method ids treated as cash on delivery.
	 *
	 * @var string[]
	 */
	private $cod_methods;

	/**
	 * Rounding policy.
	 *
	 * @var string
	 */
	private $rounding;

	/**
	 * Policy for unpaid orders that do not use a COD method.
	 *
	 * @var string
	 */
	private $unpaid_non_cod;

	/**
	 * Constructor.
	 *
	 * @param string[] $cod_methods    Payment method ids that mean cash on delivery.
	 * @param string   $rounding       One of the ROUND_* constants.
	 * @param string   $unpaid_non_cod One of the UNPAID_* constants.
	 *
	 * @throws InvalidArgumentException When a policy value is unknown.
	 */
	public function __construct( array $cod_methods = array( 'cod' ), string $rounding = self::ROUND_ROUND, string $unpaid_non_cod = self::UNPAID_COLLECT ) {
		if ( ! in_array( $rounding, array( self::ROUND_NONE, self::ROUND_ROUND, self::ROUND_CEIL, self::ROUND_FLOOR ), true ) ) {
			throw new InvalidArgumentException( 'Unknown rounding mode: ' . $rounding ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}
		if ( ! in_array( $unpaid_non_cod, array( self::UNPAID_COLLECT, self::UNPAID_ZERO ), true ) ) {
			throw new InvalidArgumentException( 'Unknown unpaid policy: ' . $unpaid_non_cod ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}
		$this->cod_methods    = array_values( array_map( 'strval', $cod_methods ) );
		$this->rounding       = $rounding;
		$this->unpaid_non_cod = $unpaid_non_cod;
	}

	/**
	 * Amount to collect for an order.
	 *
	 * @param OrderData $order Order snapshot.
	 */
	public function calculate( OrderData $order ): float {
		return $this->amount( $order->total, $order->paid_amount, $order->payment_method, $order->is_paid );
	}

	/**
	 * Amount to collect from raw values.
	 *
	 * @param float  $total          Order total including shipping.
	 * @param float  $paid           Amount already paid (advance or full payment).
	 * @param string $payment_method Payment method id.
	 * @param bool   $is_paid        Whether the store marked the order as paid.
	 */
	public function amount( float $total, float $paid, string $payment_method, bool $is_paid ): float {
		$total = max( 0.0, $total );
		$paid  = max( 0.0, $paid );

		if ( $this->is_cod( $payment_method ) ) {
			$due = $total - $paid;
		} elseif ( $is_paid ) {
			$due = 0.0;
		} elseif ( self::UNPAID_ZERO === $this->unpaid_non_cod ) {
			$due = 0.0;
		} else {
			$due = $total - $paid;
		}

		return $this->round( max( 0.0, $due ) );
	}

	/**
	 * Whether a payment method id counts as cash on delivery.
	 *
	 * @param string $payment_method Payment method id.
	 */
	public function is_cod( string $payment_method ): bool {
		return in_array( $payment_method, $this->cod_methods, true );
	}

	/**
	 * Apply the rounding policy. Floating point noise is removed first so that
	 * 1499.9999999 does not become 1500 with "ceil" rounding by accident.
	 *
	 * @param float $amount Amount.
	 */
	public function round( float $amount ): float {
		$amount = round( $amount, 2 );
		switch ( $this->rounding ) {
			case self::ROUND_ROUND:
				return (float) round( $amount );
			case self::ROUND_CEIL:
				return (float) ceil( $amount );
			case self::ROUND_FLOOR:
				return (float) floor( $amount );
			default:
				return $amount;
		}
	}
}
