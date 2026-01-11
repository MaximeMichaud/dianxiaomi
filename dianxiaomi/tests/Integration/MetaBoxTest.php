<?php
/**
 * Integration tests for meta box functionality.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

/**
 * Test meta box save/load functionality.
 */
class MetaBoxTest extends TestCase {

	/**
	 * Test saving tracking info via meta box.
	 */
	public function test_save_meta_box_saves_tracking_info(): void {
		$order = $this->create_order();
		$post  = get_post( $order->get_id() );

		$_POST['dianxiaomi_nonce']             = wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_provider'] = 'ups';
		$_POST['dianxiaomi_tracking_number']   = 'SAVE123';
		$_POST['dianxiaomi_tracking_provider_name'] = 'UPS';

		$plugin = $this->get_plugin();
		$plugin->save_meta_box( $order->get_id(), $post );

		// Refresh order from database.
		$order = wc_get_order( $order->get_id() );

		$this->assertEquals( 'ups', $order->get_meta( '_dianxiaomi_tracking_provider' ) );
		$this->assertEquals( 'SAVE123', $order->get_meta( '_dianxiaomi_tracking_number' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_provider'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider_name'] );
	}

	/**
	 * Test save_meta_box requires valid nonce.
	 */
	public function test_save_meta_box_requires_nonce(): void {
		$order = $this->create_order();
		$post  = get_post( $order->get_id() );

		$_POST['dianxiaomi_nonce']             = 'invalid_nonce';
		$_POST['dianxiaomi_tracking_provider'] = 'dhl';
		$_POST['dianxiaomi_tracking_number']   = 'INVALID123';

		$plugin = $this->get_plugin();
		$plugin->save_meta_box( $order->get_id(), $post );

		// Refresh order.
		$order = wc_get_order( $order->get_id() );

		// Should not have saved with invalid nonce.
		$this->assertEmpty( $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_provider'], $_POST['dianxiaomi_tracking_number'] );
	}

	/**
	 * Test meta box output contains required fields.
	 *
	 * @requires function woocommerce_wp_text_input
	 */
	public function test_meta_box_contains_required_fields(): void {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			$this->markTestSkipped( 'WooCommerce admin functions not available in test environment.' );
		}

		$order = $this->create_order();

		global $post;
		$post     = get_post( $order->get_id() );
		$post->ID = $order->get_id();

		$plugin = $this->get_plugin();

		ob_start();
		$plugin->meta_box();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'dianxiaomi_tracking_provider', $output );
		$this->assertStringContainsString( 'dianxiaomi_tracking_number', $output );
		$this->assertStringContainsString( 'dianxiaomi_nonce', $output );
	}

	/**
	 * Test meta box displays existing tracking info.
	 *
	 * @requires function woocommerce_wp_text_input
	 */
	public function test_meta_box_displays_existing_tracking(): void {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			$this->markTestSkipped( 'WooCommerce admin functions not available in test environment.' );
		}

		$order = $this->create_order();
		$this->set_tracking_info( $order, 'fedex', 'EXISTING123', 'FedEx' );

		global $post;
		$post     = get_post( $order->get_id() );
		$post->ID = $order->get_id();

		$plugin = $this->get_plugin();

		ob_start();
		$plugin->meta_box();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'EXISTING123', $output );
	}
}
