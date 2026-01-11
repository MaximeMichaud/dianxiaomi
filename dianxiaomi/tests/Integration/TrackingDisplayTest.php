<?php
/**
 * Integration tests for tracking info display.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

/**
 * Test tracking info display functionality.
 */
class TrackingDisplayTest extends TestCase {

	/**
	 * Test tracking info displays on order page.
	 */
	public function test_tracking_info_displays_for_valid_order(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'ups', 'TEST123456', 'UPS' );

		$plugin = $this->get_plugin();
		$plugin->plugin = 'dianxiaomi';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'TEST123456', $output );
		$this->assertStringContainsString( 'UPS', $output );
	}

	/**
	 * Test tracking info is empty for order without tracking.
	 */
	public function test_no_output_for_order_without_tracking(): void {
		$order = $this->create_order();
		// No tracking info set.

		$plugin = $this->get_plugin();
		$plugin->plugin = 'dianxiaomi';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test tracking info is empty for invalid order ID.
	 */
	public function test_no_output_for_invalid_order(): void {
		$plugin = $this->get_plugin();

		ob_start();
		$plugin->display_tracking_info( 999999 );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test tracking URL generation with custom domain.
	 */
	public function test_custom_domain_in_tracking_url(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'dhl', 'DHL789', 'DHL' );

		$plugin = $this->get_plugin();
		$plugin->plugin = 'dianxiaomi';
		$plugin->use_track_button = false;
		$plugin->custom_domain = 'https://track.example.com/?num=';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'https://track.example.com/?num=DHL789', $output );
	}

	/**
	 * Test tracking display for email (no track button).
	 */
	public function test_email_display_no_track_button(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'fedex', 'FEDEX456', 'FedEx' );

		$plugin = $this->get_plugin();
		$plugin->plugin = 'dianxiaomi';
		$plugin->use_track_button = true;

		ob_start();
		$plugin->display_tracking_info( $order->get_id(), true ); // for_email = true
		$output = ob_get_clean();

		$this->assertStringContainsString( 'FEDEX456', $output );
		// Track button should not appear in email.
		$this->assertStringNotContainsString( 'tracking-widget', $output );
	}

	/**
	 * Test track button displays when enabled.
	 */
	public function test_track_button_displays_when_enabled(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'ups', 'BUTTON123', 'UPS' );

		$plugin = $this->get_plugin();
		$plugin->plugin = 'dianxiaomi';
		$plugin->use_track_button = true;
		$plugin->custom_domain = 'https://t.17track.net/en#nums=';

		ob_start();
		$plugin->display_tracking_info( $order->get_id(), false );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'tracking-widget', $output );
		$this->assertStringContainsString( 'BUTTON123', $output );
	}
}
