<?php
/**
 * Order Test Trait.
 *
 * Provides helper methods for testing WooCommerce orders.
 *
 * @package Dianxiaomi\Tests
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests;

/**
 * Trait for order-related test helpers.
 */
trait OrderTestTrait {

	/**
	 * Create a mock order with tracking data.
	 *
	 * @param int   $id     Order ID.
	 * @param array $props  Order properties.
	 * @return \WC_Order
	 */
	protected function create_test_order( int $id = 1, array $props = array() ): \WC_Order {
		$defaults = array(
			'status'            => 'processing',
			'billing_email'     => 'test@example.com',
			'tracking_number'   => '',
			'tracking_provider' => '',
		);

		$props = array_merge( $defaults, $props );
		$order = new \WC_Order( $id );
		$order->set_status( $props['status'] );
		$order->set_billing_email( $props['billing_email'] );

		if ( ! empty( $props['tracking_number'] ) ) {
			$order->update_meta_data( '_dianxiaomi_tracking_number', $props['tracking_number'] );
		}

		if ( ! empty( $props['tracking_provider'] ) ) {
			$order->update_meta_data( '_dianxiaomi_tracking_provider', $props['tracking_provider'] );
		}

		return $order;
	}

	/**
	 * Assert that tracking meta was saved correctly.
	 *
	 * @param \WC_Order $order           The order to check.
	 * @param string    $tracking_number Expected tracking number.
	 * @param string    $provider        Expected provider name.
	 */
	protected function assert_tracking_saved( \WC_Order $order, string $tracking_number, string $provider = '' ): void {
		$this->assertSame(
			$tracking_number,
			$order->get_meta( '_dianxiaomi_tracking_number' ),
			'Tracking number should be saved'
		);

		if ( ! empty( $provider ) ) {
			$this->assertSame(
				$provider,
				$order->get_meta( '_dianxiaomi_tracking_provider' ),
				'Tracking provider should be saved'
			);
		}
	}
}
