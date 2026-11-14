<?php
/**
 * Integration tests for plugin initialization.
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

/**
 * Test plugin initialization and settings.
 */
class PluginTest extends TestCase {

	/**
	 * Test plugin is loaded.
	 */
	public function test_plugin_is_loaded(): void {
		$this->assertTrue( class_exists( 'Dianxiaomi' ) );
	}

	/**
	 * Test plugin instance is singleton.
	 */
	public function test_plugin_is_singleton(): void {
		$instance1 = \Dianxiaomi::instance();
		$instance2 = \Dianxiaomi::instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * A fresh install has no settings yet. The order meta box and customer tracking still have to be hooked.
	 */
	public function test_hooks_register_when_settings_are_missing(): void {
		$saved = get_option( 'dianxiaomi_option_name' );
		delete_option( 'dianxiaomi_option_name' );

		$reflection = new \ReflectionClass( \Dianxiaomi::class );
		$property   = $reflection->getProperty( '_instance' );
		$property->setValue( null, null );

		try {
			$plugin = \Dianxiaomi::instance();
			$this->assertNotFalse( has_action( 'add_meta_boxes', array( $plugin, 'add_meta_box' ) ) );
			$this->assertNotFalse( has_action( 'woocommerce_view_order', array( $plugin, 'display_tracking_info' ) ) );
			$this->assertNotFalse( has_action( 'woocommerce_email_before_order_table', array( $plugin, 'email_display' ) ) );
		} finally {
			if ( false === $saved ) {
				delete_option( 'dianxiaomi_option_name' );
			} else {
				update_option( 'dianxiaomi_option_name', $saved );
			}
		}
	}

	/**
	 * Test plugin registers hooks when options are set.
	 */
	public function test_plugin_registers_hooks_with_options(): void {
		// Check if the event manager and subscriber system exist.
		$this->assertTrue( class_exists( \Dianxiaomi\EventManagement\Event_Manager::class ) );
		$this->assertTrue( in_array( \Dianxiaomi\Interfaces\Subscriber_Interface::class, class_implements( \Dianxiaomi::class ), true ) );
	}

	/**
	 * Test plugin has get_subscribed_events method.
	 */
	public function test_plugin_has_subscribed_events(): void {
		$events = \Dianxiaomi::get_subscribed_events();

		$this->assertIsArray( $events );
		$this->assertArrayHasKey( 'admin_print_styles', $events );
		$this->assertArrayHasKey( 'add_meta_boxes', $events );
		$this->assertArrayHasKey( 'woocommerce_view_order', $events );
	}

	/**
	 * Test WooCommerce is active.
	 */
	public function test_woocommerce_is_active(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
	}

	/**
	 * Test plugin version constant exists.
	 */
	public function test_plugin_version_exists(): void {
		$this->assertTrue( defined( 'DIANXIAOMI_VERSION' ) );
	}

	/**
	 * The field catalog is loaded with include_once. A later instance must still see it.
	 */
	public function test_second_instance_keeps_the_field_catalog(): void {
		$reflection = new \ReflectionClass( \Dianxiaomi::class );
		$property   = $reflection->getProperty( '_instance' );
		$property->setValue( null, null );

		$plugin = \Dianxiaomi::instance();
		$ids    = array();
		foreach ( $plugin->dianxiaomi_fields as $field ) {
			if ( is_array( $field ) && isset( $field['id'] ) && is_string( $field['id'] ) ) {
				$ids[] = $field['id'];
			}
		}

		$this->assertContains( 'dianxiaomi_tracking_postal', $ids );
		$this->assertContains( 'dianxiaomi_tracking_shipdate', $ids );
	}

	/**
	 * Test plugin has expected properties.
	 */
	public function test_plugin_has_expected_properties(): void {
		$plugin = $this->get_plugin();

		$this->assertObjectHasProperty( 'plugin', $plugin );
		$this->assertObjectHasProperty( 'couriers', $plugin );
		$this->assertObjectHasProperty( 'use_track_button', $plugin );
		$this->assertObjectHasProperty( 'custom_domain', $plugin );
	}

	/**
	 * Test settings page is registered.
	 */
	public function test_settings_class_exists(): void {
		$this->assertTrue( class_exists( 'Dianxiaomi_Settings' ) );
	}

	/**
	 * Test dependencies class exists.
	 */
	public function test_dependencies_class_exists(): void {
		$this->assertTrue( class_exists( 'Dianxiaomi_Dependencies' ) );
	}

	/**
	 * Test API class exists.
	 */
	public function test_api_class_exists(): void {
		$this->assertTrue( class_exists( 'Dianxiaomi_API' ) );
	}
}
