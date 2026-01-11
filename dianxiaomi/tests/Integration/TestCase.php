<?php
/**
 * Base test case for Dianxiaomi integration tests.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Base test case with WooCommerce helpers.
 *
 * Uses PHPUnit directly instead of WP_UnitTestCase for PHPUnit 10 compatibility.
 */
abstract class TestCase extends PolyfillTestCase {

	/**
	 * Set up before each test.
	 */
	protected function set_up(): void {
		parent::set_up();
	}

	/**
	 * Tear down after each test.
	 */
	protected function tear_down(): void {
		parent::tear_down();
	}

	/**
	 * Create a WooCommerce order for testing.
	 *
	 * @param array $args Order arguments.
	 * @return \WC_Order
	 */
	protected function create_order( array $args = array() ): \WC_Order {
		$defaults = array(
			'status'      => 'processing',
			'customer_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$order = wc_create_order( $args );

		return $order;
	}

	/**
	 * Create a simple product for testing.
	 *
	 * @param array $args Product arguments.
	 * @return \WC_Product_Simple
	 */
	protected function create_product( array $args = array() ): \WC_Product_Simple {
		$defaults = array(
			'name'          => 'Test Product',
			'regular_price' => '10.00',
			'sku'           => 'TEST-' . wp_rand( 1000, 9999 ),
		);
		$args     = wp_parse_args( $args, $defaults );

		$product = new \WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_regular_price( $args['regular_price'] );
		$product->set_sku( $args['sku'] );
		$product->save();

		return $product;
	}

	/**
	 * Set tracking info on an order.
	 *
	 * @param \WC_Order $order    The order.
	 * @param string    $provider Tracking provider slug.
	 * @param string    $number   Tracking number.
	 * @param string    $name     Provider display name.
	 */
	protected function set_tracking_info( \WC_Order $order, string $provider, string $number, string $name = '' ): void {
		$order->update_meta_data( '_dianxiaomi_tracking_provider', $provider );
		$order->update_meta_data( '_dianxiaomi_tracking_number', $number );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', $name ?: $provider );
		$order->save();
	}

	/**
	 * Get the plugin instance.
	 *
	 * @return \Dianxiaomi
	 */
	protected function get_plugin(): \Dianxiaomi {
		return \Dianxiaomi::instance();
	}
}
