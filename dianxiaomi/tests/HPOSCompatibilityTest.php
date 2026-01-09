<?php
/**
 * HPOS (High-Performance Order Storage) Compatibility Tests.
 *
 * These tests verify that the plugin works correctly with WooCommerce HPOS,
 * where WC_Order objects are passed instead of WP_Post objects.
 *
 * @package Dianxiaomi
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test class for HPOS compatibility.
 */
class HPOSCompatibilityTest extends TestCase {

	/**
	 * Test that save_meta_box accepts WC_Order object (HPOS mode).
	 *
	 * In HPOS mode, WooCommerce passes a WC_Order object to the
	 * woocommerce_process_shop_order_meta hook instead of WP_Post.
	 */
	public function test_save_meta_box_accepts_wc_order(): void {
		// Setup: Create a Dianxiaomi instance.
		$dianxiaomi = Dianxiaomi::Instance();

		// Create a WC_Order object (HPOS mode).
		$order = new WC_Order( 123 );

		// Setup POST data with valid nonce.
		$_POST['dianxiaomi_nonce']            = wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number']  = 'TRACK123';
		$_POST['dianxiaomi_tracking_provider'] = 'ups';

		// This should not throw a TypeError.
		try {
			$dianxiaomi->save_meta_box( 123, $order );
			$this->assertTrue( true, 'save_meta_box accepts WC_Order without error' );
		} catch ( TypeError $e ) {
			$this->fail( 'save_meta_box should accept WC_Order object: ' . $e->getMessage() );
		}

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test that save_meta_box accepts WP_Post object (legacy mode).
	 *
	 * In legacy mode (non-HPOS), WooCommerce passes a WP_Post object.
	 */
	public function test_save_meta_box_accepts_wp_post(): void {
		// Setup: Create a Dianxiaomi instance.
		$dianxiaomi = Dianxiaomi::Instance();

		// Create a WP_Post object (legacy mode).
		$post = new WP_Post( 456 );

		// Setup POST data with valid nonce.
		$_POST['dianxiaomi_nonce']            = wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number']  = 'TRACK456';
		$_POST['dianxiaomi_tracking_provider'] = 'fedex';

		// This should not throw a TypeError.
		try {
			$dianxiaomi->save_meta_box( 456, $post );
			$this->assertTrue( true, 'save_meta_box accepts WP_Post without error' );
		} catch ( TypeError $e ) {
			$this->fail( 'save_meta_box should accept WP_Post object: ' . $e->getMessage() );
		}

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test that save_meta_box method signature uses object type hint.
	 *
	 * The second parameter must accept both WP_Post and WC_Order,
	 * so it should use 'object' type hint, not a specific class.
	 */
	public function test_save_meta_box_has_flexible_signature(): void {
		$reflection = new ReflectionMethod( Dianxiaomi::class, 'save_meta_box' );
		$params     = $reflection->getParameters();

		$this->assertCount( 2, $params, 'save_meta_box should have 2 parameters' );

		// First parameter: int $post_id.
		$this->assertEquals( 'post_id', $params[0]->getName() );
		$this->assertEquals( 'int', $params[0]->getType()->getName() );

		// Second parameter: object $post (not WP_Post).
		$this->assertEquals( 'post', $params[1]->getName() );
		$type_name = $params[1]->getType()->getName();
		$this->assertEquals(
			'object',
			$type_name,
			"Second parameter type should be 'object' to accept both WP_Post and WC_Order, got '{$type_name}'"
		);
	}

	/**
	 * Test that tracking data is saved correctly in HPOS mode.
	 */
	public function test_tracking_data_saved_with_hpos(): void {
		$dianxiaomi = Dianxiaomi::Instance();

		// Create order.
		$order = create_mock_order( 789 );

		// Setup POST data.
		$_POST['dianxiaomi_nonce']             = wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number']   = 'HPOS123456';
		$_POST['dianxiaomi_tracking_provider'] = 'dhl';

		// Save.
		$dianxiaomi->save_meta_box( 789, $order );

		// Verify meta was saved.
		$this->assertEquals( 'dhl', $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test that nonce verification works.
	 */
	public function test_save_meta_box_requires_valid_nonce(): void {
		$dianxiaomi = Dianxiaomi::Instance();
		$order      = create_mock_order( 999 );

		// No nonce - should silently return.
		$_POST['dianxiaomi_tracking_number']   = 'SHOULDNOTBESAVED';
		$_POST['dianxiaomi_tracking_provider'] = 'ups';

		$dianxiaomi->save_meta_box( 999, $order );

		// Verify meta was NOT saved (empty).
		$this->assertEmpty( $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}
}
