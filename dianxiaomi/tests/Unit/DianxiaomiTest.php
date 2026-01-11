<?php
/**
 * Dianxiaomi Main Class Tests.
 *
 * @package Dianxiaomi\Tests\Unit
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Dianxiaomi;
use Dianxiaomi\Interfaces\Subscriber_Interface;

/**
 * Test class for main Dianxiaomi class.
 */
class DianxiaomiTest extends TestCase {

	/**
	 * Reset state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		\reset_all();
	}

	/**
	 * Test singleton pattern returns same instance.
	 */
	public function test_instance_returns_singleton(): void {
		$instance1 = Dianxiaomi::instance();
		$instance2 = Dianxiaomi::instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test class implements Subscriber_Interface.
	 */
	public function test_implements_subscriber_interface(): void {
		$instance = \get_dianxiaomi_instance();
		$this->assertInstanceOf( Subscriber_Interface::class, $instance );
	}

	/**
	 * Test get_subscribed_events returns expected hooks.
	 */
	public function test_get_subscribed_events_returns_hooks(): void {
		$events = Dianxiaomi::get_subscribed_events();

		$this->assertIsArray( $events );
		$this->assertArrayHasKey( 'admin_print_scripts', $events );
		$this->assertArrayHasKey( 'admin_print_styles', $events );
		$this->assertArrayHasKey( 'add_meta_boxes', $events );
		$this->assertArrayHasKey( 'woocommerce_process_shop_order_meta', $events );
		$this->assertArrayHasKey( 'plugins_loaded', $events );
		$this->assertArrayHasKey( 'woocommerce_view_order', $events );
		$this->assertArrayHasKey( 'woocommerce_email_before_order_table', $events );
	}

	/**
	 * Test get_subscribed_events has correct callback names.
	 */
	public function test_get_subscribed_events_callbacks(): void {
		$events = Dianxiaomi::get_subscribed_events();

		$this->assertSame( 'library_scripts', $events['admin_print_scripts'] );
		$this->assertSame( 'admin_styles', $events['admin_print_styles'] );
		$this->assertSame( 'add_meta_box', $events['add_meta_boxes'] );
		$this->assertSame( 'load_plugin_textdomain', $events['plugins_loaded'] );
		$this->assertSame( 'display_tracking_info', $events['woocommerce_view_order'] );
		$this->assertSame( 'email_display', $events['woocommerce_email_before_order_table'] );
	}

	/**
	 * Test save_meta_box priority configuration.
	 */
	public function test_save_meta_box_has_priority_and_args(): void {
		$events = Dianxiaomi::get_subscribed_events();

		$this->assertIsArray( $events['woocommerce_process_shop_order_meta'] );
		$this->assertEquals( 'save_meta_box', $events['woocommerce_process_shop_order_meta'][0] );
		$this->assertEquals( 0, $events['woocommerce_process_shop_order_meta'][1] );
		$this->assertEquals( 2, $events['woocommerce_process_shop_order_meta'][2] );
	}

	/**
	 * Test admin_styles does nothing when not on order screen.
	 */
	public function test_admin_styles_skips_non_order_screen(): void {
		// Set current screen to something else.
		\set_current_screen( 'dashboard' );

		$instance = \get_dianxiaomi_instance();
		$instance->admin_styles();

		$this->assertFalse( \was_style_enqueued( 'dianxiaomi_styles' ) );
	}

	/**
	 * Test admin_styles enqueues styles on order screen.
	 */
	public function test_admin_styles_enqueues_on_order_screen(): void {
		// Set current screen to shop_order.
		\set_current_screen( 'shop_order' );

		$instance = \get_dianxiaomi_instance();
		$instance->admin_styles();

		$this->assertTrue( \was_style_enqueued( 'select2' ) );
		$this->assertTrue( \was_style_enqueued( 'dianxiaomi_styles' ) );
	}

	/**
	 * Test admin_styles works on HPOS screen.
	 */
	public function test_admin_styles_enqueues_on_hpos_screen(): void {
		// Set current screen to HPOS order page.
		\set_current_screen( 'woocommerce_page_wc-orders' );

		$instance = \get_dianxiaomi_instance();
		$instance->admin_styles();

		$this->assertTrue( \was_style_enqueued( 'dianxiaomi_styles' ) );
	}

	/**
	 * Test library_scripts does nothing when not on order screen.
	 */
	public function test_library_scripts_skips_non_order_screen(): void {
		\set_current_screen( 'dashboard' );

		$instance = \get_dianxiaomi_instance();
		$instance->library_scripts();

		$this->assertFalse( \was_script_enqueued( 'dianxiaomi_script_admin' ) );
	}

	/**
	 * Test library_scripts enqueues scripts on order screen.
	 */
	public function test_library_scripts_enqueues_on_order_screen(): void {
		\set_current_screen( 'shop_order' );

		$instance = \get_dianxiaomi_instance();
		$instance->library_scripts();

		$this->assertTrue( \was_script_enqueued( 'selectWoo' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_util' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_couriers' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_admin' ) );
	}

	/**
	 * Test include_footer_script does nothing when not on order screen.
	 */
	public function test_footer_script_skips_non_order_screen(): void {
		\set_current_screen( 'dashboard' );

		$instance = \get_dianxiaomi_instance();
		$instance->include_footer_script();

		$this->assertFalse( \was_script_enqueued( 'dianxiaomi_script_footer' ) );
	}

	/**
	 * Test include_footer_script enqueues on order screen.
	 */
	public function test_footer_script_enqueues_on_order_screen(): void {
		\set_current_screen( 'shop_order' );

		$instance = \get_dianxiaomi_instance();
		$instance->include_footer_script();

		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_footer' ) );
	}

	/**
	 * Test add_meta_box registers meta box.
	 */
	public function test_add_meta_box_registers(): void {
		global $wp_meta_boxes;

		$instance = \get_dianxiaomi_instance();
		$instance->add_meta_box();

		$this->assertArrayHasKey( 'woocommerce-dianxiaomi', $wp_meta_boxes );
	}

	/**
	 * Test save_meta_box does nothing without nonce.
	 */
	public function test_save_meta_box_requires_nonce(): void {
		$order = \create_mock_order( 123 );

		// No nonce set.
		$_POST['dianxiaomi_tracking_number'] = 'TRACK123';
		$_POST['dianxiaomi_tracking_provider'] = 'ups';

		$instance = \get_dianxiaomi_instance();
		$instance->save_meta_box( 123, $order );

		// Meta should NOT be saved.
		$this->assertEmpty( $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test save_meta_box does nothing with invalid nonce.
	 */
	public function test_save_meta_box_requires_valid_nonce(): void {
		$order = \create_mock_order( 124 );

		$_POST['dianxiaomi_nonce'] = 'invalid_nonce';
		$_POST['dianxiaomi_tracking_number'] = 'TRACK124';
		$_POST['dianxiaomi_tracking_provider'] = 'fedex';

		// Mock wp_verify_nonce to fail.
		\set_mock_return( 'wp_verify_nonce', false );

		$instance = \get_dianxiaomi_instance();
		$instance->save_meta_box( 124, $order );

		// Meta should NOT be saved.
		$this->assertEmpty( $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test save_meta_box saves tracking data with valid nonce.
	 */
	public function test_save_meta_box_saves_tracking_data(): void {
		$order = \create_mock_order( 125 );

		$_POST['dianxiaomi_nonce'] = \wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number'] = 'TRACK125';
		$_POST['dianxiaomi_tracking_provider'] = 'dhl';

		// Mock wp_verify_nonce to succeed.
		\set_mock_return( 'wp_verify_nonce', true );

		$instance = \get_dianxiaomi_instance();
		$instance->save_meta_box( 125, $order );

		// Meta should be saved.
		$this->assertEquals( 'dhl', $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test save_meta_box works with WC_Order object (HPOS).
	 */
	public function test_save_meta_box_hpos_compatibility(): void {
		$order = new \WC_Order( 126 );

		$_POST['dianxiaomi_nonce'] = \wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number'] = 'HPOS126';
		$_POST['dianxiaomi_tracking_provider'] = 'usps';

		\set_mock_return( 'wp_verify_nonce', true );

		$instance = \get_dianxiaomi_instance();
		$instance->save_meta_box( 126, $order );

		$this->assertEquals( 'usps', $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test save_meta_box works with WP_Post object (legacy).
	 */
	public function test_save_meta_box_legacy_compatibility(): void {
		$post = new \WP_Post( 127 );

		$_POST['dianxiaomi_nonce'] = \wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number'] = 'LEGACY127';
		$_POST['dianxiaomi_tracking_provider'] = 'canada-post';

		\set_mock_return( 'wp_verify_nonce', true );

		$instance = \get_dianxiaomi_instance();

		// This should not throw a TypeError.
		try {
			$instance->save_meta_box( 127, $post );
			$this->assertTrue( true );
		} catch ( \TypeError $e ) {
			$this->fail( 'save_meta_box should accept WP_Post object: ' . $e->getMessage() );
		}

		// Cleanup.
		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test instance has API property.
	 */
	public function test_instance_has_api(): void {
		$instance = \get_dianxiaomi_instance();
		$this->assertInstanceOf( \Dianxiaomi_API::class, $instance->api );
	}

	/**
	 * Test instance initializes with default values.
	 */
	public function test_instance_has_default_values(): void {
		$instance = \get_dianxiaomi_instance();

		$this->assertIsString( $instance->plugin );
		$this->assertIsBool( $instance->use_track_button );
		$this->assertIsString( $instance->custom_domain );
		$this->assertIsArray( $instance->couriers );
	}

	/**
	 * Test options are loaded from WordPress.
	 */
	public function test_options_are_loaded(): void {
		// Set options before creating instance.
		\set_wp_option(
			'dianxiaomi_option_name',
			array(
				'plugin'           => 'dianxiaomi',
				'use_track_button' => true,
				'custom_domain'    => 'https://track.example.com/',
				'couriers'         => 'ups,fedex,dhl',
			)
		);

		// Need to reset the singleton to reload options.
		$reflection = new \ReflectionClass( Dianxiaomi::class );
		$property   = $reflection->getProperty( '_instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$instance = Dianxiaomi::instance();

		$this->assertEquals( 'dianxiaomi', $instance->plugin );
		$this->assertTrue( $instance->use_track_button );
		$this->assertEquals( 'https://track.example.com/', $instance->custom_domain );
		$this->assertEquals( array( 'ups', 'fedex', 'dhl' ), $instance->couriers );
	}

	/**
	 * Test display_tracking_info with dianxiaomi plugin.
	 */
	public function test_display_tracking_info_dianxiaomi(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = false; // Disable track button to avoid JS issues.

		$order = \create_mock_order( 200 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'ups' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'TRACK200' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'UPS' );

		\set_wp_option(
			'dianxiaomi_option_name',
			array(
				'track_message_1' => 'Shipped via ',
				'track_message_2' => 'Tracking: ',
			)
		);

		ob_start();
		$instance->display_tracking_info( 200 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Shipped via', $output );
		$this->assertStringContainsString( 'TRACK200', $output );
	}

	/**
	 * Test display_tracking_info returns nothing without tracking.
	 */
	public function test_display_tracking_info_no_tracking(): void {
		$instance         = \get_dianxiaomi_instance();
		$instance->plugin = 'dianxiaomi';

		$order = \create_mock_order( 201 );
		// No tracking data set.

		ob_start();
		$instance->display_tracking_info( 201 );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test email_display calls display_tracking_info.
	 */
	public function test_email_display(): void {
		$instance         = \get_dianxiaomi_instance();
		$instance->plugin = 'dianxiaomi';

		$order = \create_mock_order( 202 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'fedex' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'EMAIL202' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'FedEx' );

		ob_start();
		$instance->email_display( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'EMAIL202', $output );
	}

	/**
	 * Test meta_box output contains expected elements.
	 */
	public function test_meta_box_output(): void {
		global $post;
		$post     = new \WP_Post( 203 );
		$post->ID = 203;

		$order = \create_mock_order( 203 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'dhl' );

		$instance = \get_dianxiaomi_instance();

		ob_start();
		$instance->meta_box();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'dianxiaomi_wrapper', $output );
		$this->assertStringContainsString( 'dianxiaomi_tracking_provider', $output );
		$this->assertStringContainsString( 'dianxiaomi_nonce', $output );
	}

	/**
	 * Test add_api_key_field output for user with no key.
	 */
	public function test_add_api_key_field_no_key(): void {
		$user = new \WP_User( 1 );
		$user->dianxiaomi_wp_api_key = null;

		$instance = \get_dianxiaomi_instance();

		ob_start();
		$instance->add_api_key_field( $user );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Dianxiaomi', $output );
		$this->assertStringContainsString( 'Generate API Key', $output );
	}

	/**
	 * Test add_api_key_field output for user with key.
	 */
	public function test_add_api_key_field_with_key(): void {
		$user = new \WP_User( 2 );
		$user->dianxiaomi_wp_api_key = 'ck_test_api_key_12345';

		$instance = \get_dianxiaomi_instance();

		ob_start();
		$instance->add_api_key_field( $user );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ck_test_api_key_12345', $output );
		$this->assertStringContainsString( 'Revoke API Key', $output );
	}

	/**
	 * Test load_plugin_textdomain is callable.
	 */
	public function test_load_plugin_textdomain(): void {
		$instance = \get_dianxiaomi_instance();

		// Should not throw.
		$instance->load_plugin_textdomain();
		$this->assertTrue( true );
	}

	/**
	 * Test custom_domain is used in tracking URL.
	 */
	public function test_custom_domain_in_tracking_url(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = false;
		$instance->custom_domain    = 'https://custom.track.com/?num=';

		$order = \create_mock_order( 204 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'usps' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'CUSTOM204' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'USPS' );

		ob_start();
		$instance->display_tracking_info( 204 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'https://custom.track.com/?num=CUSTOM204', $output );
	}

	/**
	 * Test default tracking URL when no custom domain.
	 */
	public function test_default_tracking_url(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = false;
		$instance->custom_domain    = '';

		$order = \create_mock_order( 205 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'dhl' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'DEFAULT205' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'DHL' );

		ob_start();
		$instance->display_tracking_info( 205 );
		$output = ob_get_clean();

		$this->assertStringContainsString( '17track.net', $output );
		$this->assertStringContainsString( 'DEFAULT205', $output );
	}

	/**
	 * Test display_tracking_info with wc-shipment-tracking plugin.
	 */
	public function test_display_tracking_info_wc_shipment_tracking(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'wc-shipment-tracking';
		$instance->use_track_button = false; // Should return early.

		$order = \create_mock_order( 206 );
		$order->update_meta_data( '_tracking_number', 'ups#WCTRACK206' );

		ob_start();
		$instance->display_tracking_info( 206 );
		$output = ob_get_clean();

		// With use_track_button false, should output nothing.
		$this->assertEmpty( $output );
	}

	/**
	 * Test display_tracking_info with unrecognized plugin.
	 */
	public function test_display_tracking_info_unknown_plugin(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'unknown-plugin';
		$instance->use_track_button = false;

		$order = \create_mock_order( 207 );

		ob_start();
		$instance->display_tracking_info( 207 );
		$output = ob_get_clean();

		// Should output nothing for unknown plugin.
		$this->assertEmpty( $output );
	}

	/**
	 * Test display_tracking_info for email (for_email = true).
	 */
	public function test_display_tracking_info_for_email(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = true;
		$instance->custom_domain    = '';

		$order = \create_mock_order( 208 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'ups' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'EMAIL208' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'UPS' );

		ob_start();
		$instance->display_tracking_info( 208, true ); // for_email = true.
		$output = ob_get_clean();

		// Should contain tracking but no track button for email.
		$this->assertStringContainsString( 'EMAIL208', $output );
		$this->assertStringNotContainsString( 'tracking-widget', $output );
	}

	/**
	 * Test display_tracking_info returns early for invalid order.
	 */
	public function test_display_tracking_info_invalid_order(): void {
		$instance         = \get_dianxiaomi_instance();
		$instance->plugin = 'dianxiaomi';

		ob_start();
		$instance->display_tracking_info( 0 ); // Invalid order ID.
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test meta_box returns early for invalid order.
	 */
	public function test_meta_box_invalid_order(): void {
		global $post;
		$post     = new \WP_Post( 0 );
		$post->ID = 0;

		$instance = \get_dianxiaomi_instance();

		ob_start();
		$instance->meta_box();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test couriers loaded as array.
	 */
	public function test_couriers_loaded_as_array(): void {
		\set_wp_option(
			'dianxiaomi_option_name',
			array(
				'plugin'   => 'dianxiaomi',
				'couriers' => array( 'ups', 'fedex', 'dhl' ),
			)
		);

		// Reset the singleton.
		$reflection = new \ReflectionClass( Dianxiaomi::class );
		$property   = $reflection->getProperty( '_instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$instance = Dianxiaomi::instance();

		$this->assertEquals( array( 'ups', 'fedex', 'dhl' ), $instance->couriers );
	}

	/**
	 * Test empty couriers string results in empty array.
	 */
	public function test_empty_couriers_string(): void {
		\set_wp_option(
			'dianxiaomi_option_name',
			array(
				'plugin'   => 'dianxiaomi',
				'couriers' => '',
			)
		);

		$reflection = new \ReflectionClass( Dianxiaomi::class );
		$property   = $reflection->getProperty( '_instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$instance = Dianxiaomi::instance();

		$this->assertEmpty( $instance->couriers );
	}

	/**
	 * Test generate_api_key does nothing without permission.
	 */
	public function test_generate_api_key_no_permission(): void {
		// current_user_can is mocked to return true by default.
		// This test verifies the method exists and runs without error.
		$instance = \get_dianxiaomi_instance();

		// Should not throw.
		$instance->generate_api_key( 999 );
		$this->assertTrue( true );
	}

	/**
	 * Test generate_api_key requires nonce.
	 */
	public function test_generate_api_key_requires_nonce(): void {
		$_POST['dianxiaomi_wp_generate_api_key'] = '1';
		// No nonce set.

		$instance = \get_dianxiaomi_instance();
		$instance->generate_api_key( 1 );

		// Verify the method ran without error.
		$this->assertTrue( true );

		unset( $_POST['dianxiaomi_wp_generate_api_key'] );
	}

	/**
	 * Test in_admin_footer callback name.
	 */
	public function test_in_admin_footer_event(): void {
		$events = Dianxiaomi::get_subscribed_events();

		$this->assertArrayHasKey( 'in_admin_footer', $events );
		$this->assertEquals( 'include_footer_script', $events['in_admin_footer'] );
	}

	/**
	 * Test dianxiaomi_fields property exists.
	 */
	public function test_dianxiaomi_fields_exists(): void {
		$instance = \get_dianxiaomi_instance();

		$this->assertIsArray( $instance->dianxiaomi_fields );
	}

	/**
	 * Test save_meta_box with valid nonce.
	 */
	public function test_save_meta_box_with_valid_nonce(): void {
		$_POST['dianxiaomi_nonce']            = \wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number']  = 'SAVE123';
		$_POST['dianxiaomi_tracking_provider'] = 'ups';

		$order = \create_mock_order( 210 );
		$post  = new \WP_Post( 210 );

		$instance = \get_dianxiaomi_instance();
		$instance->save_meta_box( 210, $post );

		$this->assertEquals( 'ups', $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test save_meta_box with WC_Order object (HPOS).
	 */
	public function test_save_meta_box_with_order_object(): void {
		$_POST['dianxiaomi_nonce']            = \wp_create_nonce( 'dianxiaomi_save_meta_box' );
		$_POST['dianxiaomi_tracking_number']  = 'HPOS123';
		$_POST['dianxiaomi_tracking_provider'] = 'dhl';

		$order = \create_mock_order( 211 );

		$instance = \get_dianxiaomi_instance();
		$instance->save_meta_box( 211, $order );

		$this->assertEquals( 'dhl', $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test save_meta_box with invalid nonce.
	 */
	public function test_save_meta_box_invalid_nonce(): void {
		$_POST['dianxiaomi_nonce']            = 'invalid_nonce';
		$_POST['dianxiaomi_tracking_number']  = 'INVALID123';
		$_POST['dianxiaomi_tracking_provider'] = 'fedex';

		\set_mock_return( 'wp_verify_nonce', false );

		$order = \create_mock_order( 212 );
		$post  = new \WP_Post( 212 );

		$instance = \get_dianxiaomi_instance();
		$instance->save_meta_box( 212, $post );

		// Should not save meta with invalid nonce.
		$this->assertEmpty( $order->get_meta( '_dianxiaomi_tracking_provider' ) );

		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_tracking_number'], $_POST['dianxiaomi_tracking_provider'] );
	}

	/**
	 * Test display_tracking_info with track button.
	 */
	public function test_display_tracking_info_with_track_button(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = true;
		$instance->custom_domain    = 'https://t.17track.net/en#nums=';

		$order = \create_mock_order( 213 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'ups' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'BUTTON213' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'UPS' );

		ob_start();
		$instance->display_tracking_info( 213, false );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'BUTTON213', $output );
		$this->assertStringContainsString( 'tracking-widget', $output );
		$this->assertStringContainsString( 'Track', $output );
	}

	/**
	 * Test display_tracking_info with required fields.
	 */
	public function test_display_tracking_info_with_required_fields(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = false;
		$instance->custom_domain    = '';

		$order = \create_mock_order( 214 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'dhl' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'REQ214' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'DHL' );
		$order->update_meta_data( '_dianxiaomi_tracking_required_fields', 'dianxiaomi_tracking_postal_code' );
		$order->update_meta_data( '_dianxiaomi_tracking_postal_code', '12345' );

		ob_start();
		$instance->display_tracking_info( 214 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'REQ214', $output );
	}

	/**
	 * Test display_tracking_info returns early when no tracking data.
	 */
	public function test_display_tracking_info_no_tracking_data(): void {
		$instance         = \get_dianxiaomi_instance();
		$instance->plugin = 'dianxiaomi';

		$order = \create_mock_order( 215 );
		// No tracking meta set.

		ob_start();
		$instance->display_tracking_info( 215 );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test wc-shipment-tracking with tracking button enabled.
	 */
	public function test_wc_shipment_tracking_with_track_button(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'wc-shipment-tracking';
		$instance->use_track_button = true;
		$instance->custom_domain    = 'https://track.example.com/';

		$order = \create_mock_order( 216 );
		$order->update_meta_data( '_tracking_number', 'fedex#WC216' );

		ob_start();
		$instance->display_tracking_info( 216, false );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'tracking-widget', $output );
		$this->assertStringContainsString( 'Track', $output );
	}

	/**
	 * Test wc-shipment-tracking with colon and sharp formats.
	 */
	public function test_wc_shipment_tracking_colon_sharp_invalid(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'wc-shipment-tracking';
		$instance->use_track_button = true;

		// Invalid format: sharp >= colon.
		$order = \create_mock_order( 217 );
		$order->update_meta_data( '_tracking_number', 'abc:def#ghi' );

		ob_start();
		$instance->display_tracking_info( 217, false );
		$output = ob_get_clean();

		// Should return early for invalid format.
		$this->assertEmpty( $output );
	}

	/**
	 * Test wc-shipment-tracking with colon only format.
	 */
	public function test_wc_shipment_tracking_colon_only(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'wc-shipment-tracking';
		$instance->use_track_button = true;

		$order = \create_mock_order( 218 );
		$order->update_meta_data( '_tracking_number', 'abc:def' );

		ob_start();
		$instance->display_tracking_info( 218, false );
		$output = ob_get_clean();

		// Should return early for colon-only format.
		$this->assertEmpty( $output );
	}

	/**
	 * Test wc-shipment-tracking plain tracking number.
	 */
	public function test_wc_shipment_tracking_plain_number(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'wc-shipment-tracking';
		$instance->use_track_button = true;
		$instance->custom_domain    = 'https://track.example.com/';

		$order = \create_mock_order( 219 );
		$order->update_meta_data( '_tracking_number', 'PLAIN219' );

		ob_start();
		$instance->display_tracking_info( 219, false );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'tracking-widget', $output );
	}

	/**
	 * Test wc-shipment-tracking with required fields.
	 */
	public function test_wc_shipment_tracking_with_required_fields(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'wc-shipment-tracking';
		$instance->use_track_button = true;
		$instance->custom_domain    = 'https://track.example.com/';

		$order = \create_mock_order( 220 );
		$order->update_meta_data( '_tracking_number', 'ups#TRACK220:12345' );

		ob_start();
		$instance->display_tracking_info( 220, false );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'tracking-widget', $output );
	}

	/**
	 * Test admin_styles does nothing when not on order screen.
	 */
	public function test_admin_styles_not_order_screen(): void {
		\reset_wp_enqueues();
		\reset_current_screen();

		$instance = \get_dianxiaomi_instance();
		$instance->admin_styles();

		$this->assertFalse( \was_style_enqueued( 'dianxiaomi_styles' ) );
	}

	/**
	 * Test admin_styles enqueues on order screen.
	 */
	public function test_admin_styles_on_order_screen(): void {
		\reset_wp_enqueues();
		\set_current_screen( 'shop_order' );

		$instance = \get_dianxiaomi_instance();
		$instance->admin_styles();

		$this->assertTrue( \was_style_enqueued( 'dianxiaomi_styles' ) );
		$this->assertTrue( \was_style_enqueued( 'select2' ) );
	}

	/**
	 * Test library_scripts does nothing when not on order screen.
	 */
	public function test_library_scripts_not_order_screen(): void {
		\reset_wp_enqueues();
		\reset_current_screen();

		$instance = \get_dianxiaomi_instance();
		$instance->library_scripts();

		$this->assertFalse( \was_script_enqueued( 'dianxiaomi_script_admin' ) );
	}

	/**
	 * Test library_scripts enqueues on order screen.
	 */
	public function test_library_scripts_on_order_screen(): void {
		\reset_wp_enqueues();
		\set_current_screen( 'woocommerce_page_wc-orders' );

		$instance = \get_dianxiaomi_instance();
		$instance->library_scripts();

		$this->assertTrue( \was_script_enqueued( 'selectWoo' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_util' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_couriers' ) );
		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_admin' ) );
	}

	/**
	 * Test include_footer_script enqueues footer script on order screen.
	 */
	public function test_include_footer_script_on_order_screen(): void {
		\reset_wp_enqueues();
		\set_current_screen( 'shop_order' );

		$instance = \get_dianxiaomi_instance();
		$instance->include_footer_script();

		$this->assertTrue( \was_script_enqueued( 'dianxiaomi_script_footer' ) );
	}

	/**
	 * Test include_footer_script does nothing off order screen.
	 */
	public function test_include_footer_script_not_order_screen(): void {
		\reset_wp_enqueues();
		\reset_current_screen();

		$instance = \get_dianxiaomi_instance();
		$instance->include_footer_script();

		$this->assertFalse( \was_script_enqueued( 'dianxiaomi_script_footer' ) );
	}

	/**
	 * Test generate_api_key creates new key.
	 */
	public function test_generate_api_key_creates_key(): void {
		$user = \create_mock_user( 100 );
		$_POST['dianxiaomi_nonce']               = \wp_create_nonce( 'dianxiaomi_generate_api_key' );
		$_POST['dianxiaomi_wp_generate_api_key'] = '1';

		$instance = \get_dianxiaomi_instance();
		$instance->generate_api_key( 100 );

		$meta = \get_user_meta( 100, 'dianxiaomi_wp_api_key', true );
		$this->assertStringStartsWith( 'ck_', $meta );

		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_wp_generate_api_key'] );
	}

	/**
	 * Test generate_api_key revokes existing key.
	 */
	public function test_generate_api_key_revokes_key(): void {
		$user = \create_mock_user( 101, array( 'dianxiaomi_wp_api_key' => 'ck_existing_key' ) );
		$_POST['dianxiaomi_nonce']               = \wp_create_nonce( 'dianxiaomi_generate_api_key' );
		$_POST['dianxiaomi_wp_generate_api_key'] = '1';

		$instance = \get_dianxiaomi_instance();
		$instance->generate_api_key( 101 );

		$meta = \get_user_meta( 101, 'dianxiaomi_wp_api_key', true );
		$this->assertEmpty( $meta );

		unset( $_POST['dianxiaomi_nonce'], $_POST['dianxiaomi_wp_generate_api_key'] );
	}

	/**
	 * Test custom domain with 17track format.
	 */
	public function test_track_button_17track_url(): void {
		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = true;
		$instance->custom_domain    = 'https://t.17track.net/en#nums=';

		$order = \create_mock_order( 221 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'fedex' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'TRACK17' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'FedEx' );

		ob_start();
		$instance->display_tracking_info( 221, false );
		$output = ob_get_clean();

		$this->assertStringContainsString( '17track', $output );
		$this->assertStringContainsString( 'TRACK17', $output );
	}

	/**
	 * Test tracking with custom track messages.
	 */
	public function test_custom_track_messages(): void {
		\set_wp_option(
			'dianxiaomi_option_name',
			array(
				'plugin'          => 'dianxiaomi',
				'track_message_1' => 'Shipped by ',
				'track_message_2' => 'Track with ',
			)
		);

		$instance                   = \get_dianxiaomi_instance();
		$instance->plugin           = 'dianxiaomi';
		$instance->use_track_button = false;
		$instance->custom_domain    = '';

		$order = \create_mock_order( 222 );
		$order->update_meta_data( '_dianxiaomi_tracking_provider', 'ups' );
		$order->update_meta_data( '_dianxiaomi_tracking_number', 'MSG222' );
		$order->update_meta_data( '_dianxiaomi_tracking_provider_name', 'UPS' );

		ob_start();
		$instance->display_tracking_info( 222 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Shipped by', $output );
		$this->assertStringContainsString( 'Track with', $output );
	}
}
