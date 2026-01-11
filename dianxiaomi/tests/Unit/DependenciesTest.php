<?php
/**
 * Dianxiaomi Dependencies Tests.
 *
 * @package Dianxiaomi\Tests\Unit
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Dianxiaomi_Dependencies;
use ReflectionClass;

/**
 * Test class for Dianxiaomi_Dependencies.
 */
class DependenciesTest extends TestCase {

	/**
	 * Reset state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		\reset_all();
		// Reset the static active_plugins array.
		$reflection = new ReflectionClass( Dianxiaomi_Dependencies::class );
		$property   = $reflection->getProperty( 'active_plugins' );
		$property->setAccessible( true );
		$property->setValue( null, array() );
	}

	/**
	 * Test init loads active plugins from options.
	 */
	public function test_init_loads_active_plugins(): void {
		\set_wp_option( 'active_plugins', array( 'plugin-a/plugin-a.php', 'plugin-b/plugin-b.php' ) );

		Dianxiaomi_Dependencies::init();

		// Check that plugin_active_check works after init.
		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( 'plugin-a/plugin-a.php' ) );
		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( 'plugin-b/plugin-b.php' ) );
	}

	/**
	 * Test plugin_active_check returns true for active plugin.
	 */
	public function test_plugin_active_check_returns_true_for_active(): void {
		\set_wp_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );

		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( 'woocommerce/woocommerce.php' ) );
	}

	/**
	 * Test plugin_active_check returns false for inactive plugin.
	 */
	public function test_plugin_active_check_returns_false_for_inactive(): void {
		\set_wp_option( 'active_plugins', array( 'other-plugin/other.php' ) );

		$this->assertFalse( Dianxiaomi_Dependencies::plugin_active_check( 'woocommerce/woocommerce.php' ) );
	}

	/**
	 * Test plugin_active_check accepts array of plugins.
	 */
	public function test_plugin_active_check_accepts_array(): void {
		\set_wp_option( 'active_plugins', array( 'plugin-b/plugin-b.php' ) );

		$plugins = array( 'plugin-a/plugin-a.php', 'plugin-b/plugin-b.php' );
		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( $plugins ) );
	}

	/**
	 * Test plugin_active_check returns false when no array match.
	 */
	public function test_plugin_active_check_array_no_match(): void {
		\set_wp_option( 'active_plugins', array( 'other/other.php' ) );

		$plugins = array( 'plugin-a/plugin-a.php', 'plugin-b/plugin-b.php' );
		$this->assertFalse( Dianxiaomi_Dependencies::plugin_active_check( $plugins ) );
	}

	/**
	 * Test woocommerce_active_check returns true when WC active.
	 */
	public function test_woocommerce_active_check_returns_true(): void {
		\set_wp_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );

		$this->assertTrue( Dianxiaomi_Dependencies::woocommerce_active_check() );
	}

	/**
	 * Test woocommerce_active_check returns false when WC inactive.
	 */
	public function test_woocommerce_active_check_returns_false(): void {
		\set_wp_option( 'active_plugins', array( 'other-plugin/other.php' ) );

		$this->assertFalse( Dianxiaomi_Dependencies::woocommerce_active_check() );
	}

	/**
	 * Test plugin_active_check auto-initializes if not init'd.
	 */
	public function test_plugin_active_check_auto_initializes(): void {
		\set_wp_option( 'active_plugins', array( 'auto-init/auto-init.php' ) );

		// Don't call init(), just call plugin_active_check directly.
		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( 'auto-init/auto-init.php' ) );
	}

	/**
	 * Test plugin check works with associative array keys (multisite format).
	 */
	public function test_plugin_active_check_with_array_keys(): void {
		// Multisite stores plugins as keys.
		\set_wp_option( 'active_plugins', array( 'plugin-key/plugin.php' => time() ) );

		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( 'plugin-key/plugin.php' ) );
	}

	/**
	 * Test empty active_plugins option.
	 */
	public function test_empty_active_plugins(): void {
		\set_wp_option( 'active_plugins', array() );

		$this->assertFalse( Dianxiaomi_Dependencies::plugin_active_check( 'any/plugin.php' ) );
	}

	/**
	 * Test plugin_active_check array with matching key.
	 */
	public function test_plugin_active_check_array_with_key_match(): void {
		// Test array_key_exists path.
		\set_wp_option( 'active_plugins', array( 'plugin-a/plugin-a.php' => '1234' ) );

		$plugins = array( 'plugin-a/plugin-a.php', 'plugin-b/plugin-b.php' );
		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( $plugins ) );
	}

	/**
	 * Test init in multisite environment.
	 */
	public function test_init_multisite(): void {
		// Enable multisite mock.
		\set_mock_return( 'is_multisite', true );

		// Set regular plugins.
		\set_wp_option( 'active_plugins', array( 'plugin-a/plugin-a.php' ) );

		// Set network-activated plugins (associative array with timestamp).
		\set_site_option( 'active_sitewide_plugins', array( 'network-plugin/network.php' => time() ) );

		// Reset static state.
		$reflection = new ReflectionClass( Dianxiaomi_Dependencies::class );
		$property   = $reflection->getProperty( 'active_plugins' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		Dianxiaomi_Dependencies::init();

		// Both regular and network plugins should be active.
		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( 'plugin-a/plugin-a.php' ) );
		$this->assertTrue( Dianxiaomi_Dependencies::plugin_active_check( 'network-plugin/network.php' ) );
	}
}
