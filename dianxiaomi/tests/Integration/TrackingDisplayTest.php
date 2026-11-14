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

	/**
	 * Courier definitions store required field keys, not meta field ids.
	 */
	public function test_required_courier_fields_are_shown_with_the_tracking_number(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'deutsch-post', 'DE123', 'Deutsche Post' );
		$order->update_meta_data( '_dianxiaomi_tracking_postal', '75001' );
		$order->update_meta_data( '_dianxiaomi_tracking_required_fields', 'tracking_postal_code' );
		$order->save();

		$plugin                     = $this->get_plugin();
		$plugin->plugin             = 'dianxiaomi';
		$plugin->use_track_button   = true;
		$plugin->custom_domain      = 'https://t.17track.net/en#nums=';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertStringContainsString( '(75001)', $output );
		$this->assertMatchesRegularExpression( '#<a class="btn" href="[^"]*nums=DE123:75001#', $output );
	}

	/**
	 * A custom tracking prefix has to carry the number on the button itself.
	 */
	public function test_custom_domain_track_button_includes_the_tracking_number(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'ups', 'BUTTON999', 'UPS' );

		$plugin                   = $this->get_plugin();
		$plugin->plugin           = 'dianxiaomi';
		$plugin->use_track_button = true;
		$plugin->custom_domain    = 'https://track.example.com/?num=';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'#<a class="btn" href="https://track\.example\.com/\?num=BUTTON999"#',
			$output
		);
		$this->assertStringContainsString( '<style>', $output );
		$this->assertStringNotContainsString( '</a>.btn{', $output );
	}

	/**
	 * A domain without a scheme must stay protocol-relative after escaping.
	 */
	public function test_schemeless_track_button_stays_protocol_relative(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'ups', 'SCHEME1', 'UPS' );

		$plugin                   = $this->get_plugin();
		$plugin->plugin           = 'dianxiaomi';
		$plugin->use_track_button = true;
		$plugin->custom_domain    = 'track.example.com/?num=';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'#<a class="btn" href="//track\.example\.com/\?num=SCHEME1"#',
			$output
		);
		$this->assertMatchesRegularExpression( '#<a target="_blank" href="//track\.example\.com/\?num=SCHEME1"#', $output );
		$this->assertStringNotContainsString( 'http://http://', $output );
		$this->assertStringNotContainsString( '//http://', $output );
	}

	/**
	 * An empty custom domain still has to open the same default tracker as the text link.
	 */
	public function test_track_button_uses_the_default_tracker_when_domain_is_empty(): void {
		$order = $this->create_order();
		$this->set_tracking_info( $order, 'ups', 'DEFAULT1', 'UPS' );

		$plugin                   = $this->get_plugin();
		$plugin->plugin           = 'dianxiaomi';
		$plugin->use_track_button = true;
		$plugin->custom_domain    = '';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'href="https://t.17track.net/en#nums=DEFAULT1&pf=wc_d&pf_c=ups"',
			html_entity_decode( $output, ENT_QUOTES, 'UTF-8' )
		);
	}

	/**
	 * Shipment Tracking stores provider, number, and extra fields in one meta value.
	 */
	public function test_shipment_tracking_button_does_not_repeat_the_tracking_number(): void {
		$order = $this->create_order();
		$order->update_meta_data( '_tracking_number', 'ups#1Z999:75001' );
		$order->save();

		$plugin                   = $this->get_plugin();
		$plugin->plugin           = 'wc-shipment-tracking';
		$plugin->use_track_button = true;
		$plugin->custom_domain    = 'https://track.example.com/?num=';

		ob_start();
		$plugin->display_tracking_info( $order->get_id() );
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'#<a class="btn" href="https://track\.example\.com/\?num=1Z999:75001"#',
			$output
		);
		$this->assertStringNotContainsString( '1Z999:1Z999', $output );
	}
}
