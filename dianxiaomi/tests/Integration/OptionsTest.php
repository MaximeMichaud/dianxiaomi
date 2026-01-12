<?php
/**
 * Integration tests for plugin options initialization.
 *
 * Tests that WordPress option storage formats are correctly handled.
 * WordPress converts checkbox values in various ways depending on how
 * they're saved (true -> '1' string, 1 -> '1' string, etc.).
 *
 * @package Dianxiaomi\Tests\Integration
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Integration;

use ReflectionClass;

/**
 * Test options initialization with various WordPress storage formats.
 */
class OptionsTest extends TestCase {

	/**
	 * Reset the singleton instance before each test.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->reset_singleton();
		delete_option( 'dianxiaomi_option_name' );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tear_down(): void {
		delete_option( 'dianxiaomi_option_name' );
		$this->reset_singleton();
		parent::tear_down();
	}

	/**
	 * Reset the Dianxiaomi singleton instance using reflection.
	 */
	private function reset_singleton(): void {
		$reflection = new ReflectionClass( \Dianxiaomi::class );
		$property   = $reflection->getProperty( '_instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Test use_track_button with string '1' (WordPress checkbox storage format).
	 *
	 * When WordPress saves a checkbox, it stores the value as string '1'.
	 * This is the most common format encountered in production.
	 */
	public function test_use_track_button_with_string_one(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => '1' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertTrue( $plugin->use_track_button, 'use_track_button should be true when option is string "1"' );
	}

	/**
	 * Test use_track_button with integer 1.
	 *
	 * Some code paths may store as integer.
	 */
	public function test_use_track_button_with_integer_one(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => 1 )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertTrue( $plugin->use_track_button, 'use_track_button should be true when option is integer 1' );
	}

	/**
	 * Test use_track_button with boolean true.
	 *
	 * Direct boolean storage (less common but possible).
	 */
	public function test_use_track_button_with_boolean_true(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => true )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertTrue( $plugin->use_track_button, 'use_track_button should be true when option is boolean true' );
	}

	/**
	 * Test use_track_button with string 'yes'.
	 *
	 * Some WordPress plugins use 'yes'/'no' format.
	 */
	public function test_use_track_button_with_string_yes(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => 'yes' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertTrue( $plugin->use_track_button, 'use_track_button should be true when option is string "yes"' );
	}

	/**
	 * Test use_track_button defaults to false when not set.
	 */
	public function test_use_track_button_defaults_to_false(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'plugin' => 'dianxiaomi' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertFalse( $plugin->use_track_button, 'use_track_button should be false when not set' );
	}

	/**
	 * Test use_track_button with empty string.
	 */
	public function test_use_track_button_with_empty_string(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => '' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertFalse( $plugin->use_track_button, 'use_track_button should be false when option is empty string' );
	}

	/**
	 * Test use_track_button with string '0'.
	 */
	public function test_use_track_button_with_string_zero(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => '0' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertFalse( $plugin->use_track_button, 'use_track_button should be false when option is string "0"' );
	}

	/**
	 * Test use_track_button with boolean false.
	 */
	public function test_use_track_button_with_boolean_false(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => false )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertFalse( $plugin->use_track_button, 'use_track_button should be false when option is boolean false' );
	}

	/**
	 * Test couriers with comma-separated string (settings page format).
	 */
	public function test_couriers_with_comma_separated_string(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'couriers' => 'fedex,ups,dhl' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertIsArray( $plugin->couriers );
		$this->assertCount( 3, $plugin->couriers );
		$this->assertContains( 'fedex', $plugin->couriers );
		$this->assertContains( 'ups', $plugin->couriers );
		$this->assertContains( 'dhl', $plugin->couriers );
	}

	/**
	 * Test couriers with array format.
	 */
	public function test_couriers_with_array(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'couriers' => array( 'fedex', 'ups', 'dhl' ) )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertIsArray( $plugin->couriers );
		$this->assertCount( 3, $plugin->couriers );
		$this->assertContains( 'fedex', $plugin->couriers );
	}

	/**
	 * Test couriers with empty string.
	 */
	public function test_couriers_with_empty_string(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'couriers' => '' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertIsArray( $plugin->couriers );
		$this->assertEmpty( $plugin->couriers );
	}

	/**
	 * Test couriers defaults to empty array.
	 */
	public function test_couriers_defaults_to_empty_array(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'plugin' => 'dianxiaomi' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertIsArray( $plugin->couriers );
		$this->assertEmpty( $plugin->couriers );
	}

	/**
	 * Test plugin option with string value.
	 */
	public function test_plugin_option_with_string(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'plugin' => 'wc-shipment-tracking' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertSame( 'wc-shipment-tracking', $plugin->plugin );
	}

	/**
	 * Test plugin option defaults to empty string.
	 */
	public function test_plugin_option_defaults_to_empty(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'use_track_button' => '1' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertSame( '', $plugin->plugin );
	}

	/**
	 * Test custom_domain option with string value.
	 */
	public function test_custom_domain_with_string(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'custom_domain' => 'https://track.example.com/' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertSame( 'https://track.example.com/', $plugin->custom_domain );
	}

	/**
	 * Test custom_domain defaults to empty string.
	 */
	public function test_custom_domain_defaults_to_empty(): void {
		update_option(
			'dianxiaomi_option_name',
			array( 'plugin' => 'dianxiaomi' )
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertSame( '', $plugin->custom_domain );
	}

	/**
	 * Test all options combined.
	 */
	public function test_all_options_combined(): void {
		update_option(
			'dianxiaomi_option_name',
			array(
				'plugin'           => 'dianxiaomi',
				'use_track_button' => '1',
				'custom_domain'    => 'https://track.example.com/',
				'couriers'         => 'fedex,ups',
			)
		);

		$plugin = \Dianxiaomi::instance();

		$this->assertSame( 'dianxiaomi', $plugin->plugin );
		$this->assertTrue( $plugin->use_track_button );
		$this->assertSame( 'https://track.example.com/', $plugin->custom_domain );
		$this->assertCount( 2, $plugin->couriers );
	}

	/**
	 * Test behavior when no options exist.
	 */
	public function test_no_options_exist(): void {
		// Ensure option doesn't exist.
		delete_option( 'dianxiaomi_option_name' );

		$plugin = \Dianxiaomi::instance();

		$this->assertSame( '', $plugin->plugin );
		$this->assertFalse( $plugin->use_track_button );
		$this->assertSame( '', $plugin->custom_domain );
		$this->assertEmpty( $plugin->couriers );
	}
}
