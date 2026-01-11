<?php
/**
 * Dianxiaomi Functions Tests.
 *
 * @package Dianxiaomi\Tests\Unit
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test class for dianxiaomi-functions.php.
 */
class FunctionsTest extends TestCase {

	/**
	 * Reset state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		\reset_all();
	}

	/**
	 * Test is_woocommerce_active function exists.
	 */
	public function test_is_woocommerce_active_exists(): void {
		$this->assertTrue( function_exists( 'is_woocommerce_active' ) );
	}

	/**
	 * Test is_woocommerce_active returns true when WC active.
	 */
	public function test_is_woocommerce_active_returns_true(): void {
		\set_wp_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );

		// Reset Dependencies static.
		$reflection = new \ReflectionClass( \Dianxiaomi_Dependencies::class );
		$property   = $reflection->getProperty( 'active_plugins' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		$this->assertTrue( \is_woocommerce_active() );
	}

	/**
	 * Test is_woocommerce_active returns false when WC inactive.
	 */
	public function test_is_woocommerce_active_returns_false(): void {
		\set_wp_option( 'active_plugins', array() );

		// Reset Dependencies static.
		$reflection = new \ReflectionClass( \Dianxiaomi_Dependencies::class );
		$property   = $reflection->getProperty( 'active_plugins' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		$this->assertFalse( \is_woocommerce_active() );
	}

	/**
	 * Test get_dianxiaomi_currency function exists.
	 */
	public function test_get_dianxiaomi_currency_exists(): void {
		$this->assertTrue( function_exists( 'get_dianxiaomi_currency' ) );
	}

	/**
	 * Test get_dianxiaomi_currency returns USD by default.
	 */
	public function test_get_dianxiaomi_currency_default(): void {
		// When get_woocommerce_currency doesn't exist, should return USD.
		$result = \get_dianxiaomi_currency();
		$this->assertIsString( $result );
	}

	/**
	 * Test get_dianxiaomi_currency_symbol function exists.
	 */
	public function test_get_dianxiaomi_currency_symbol_exists(): void {
		$this->assertTrue( function_exists( 'get_dianxiaomi_currency_symbol' ) );
	}

	/**
	 * Test get_dianxiaomi_currency_symbol returns $ by default.
	 */
	public function test_get_dianxiaomi_currency_symbol_default(): void {
		$result = \get_dianxiaomi_currency_symbol();
		$this->assertIsString( $result );
	}

	/**
	 * Test get_dianxiaomi_api_url function exists.
	 */
	public function test_get_dianxiaomi_api_url_exists(): void {
		$this->assertTrue( function_exists( 'get_dianxiaomi_api_url' ) );
	}

	/**
	 * Test get_dianxiaomi_api_url returns correct base URL.
	 */
	public function test_get_dianxiaomi_api_url_base(): void {
		$url = \get_dianxiaomi_api_url();
		$this->assertStringContainsString( '/dianxiaomi-api/v1', $url );
	}

	/**
	 * Test get_dianxiaomi_api_url appends route.
	 */
	public function test_get_dianxiaomi_api_url_with_route(): void {
		$url = \get_dianxiaomi_api_url( '/orders' );
		$this->assertStringContainsString( '/dianxiaomi-api/v1/orders', $url );
	}

	/**
	 * Test dianxiaomi_wpdate function exists.
	 */
	public function test_dianxiaomi_wpdate_exists(): void {
		$this->assertTrue( function_exists( 'dianxiaomi_wpdate' ) );
	}

	/**
	 * Test dianxiaomi_wpdate formats date correctly.
	 */
	public function test_dianxiaomi_wpdate_formats_date(): void {
		$timestamp = strtotime( '2024-01-15 10:30:00' );
		$result    = \dianxiaomi_wpdate( 'Y-m-d', $timestamp );

		$this->assertEquals( '2024-01-15', $result );
	}

	/**
	 * Test dianxiaomi_wpdate with time format.
	 */
	public function test_dianxiaomi_wpdate_formats_time(): void {
		$timestamp = strtotime( '2024-06-20 14:45:30' );
		$result    = \dianxiaomi_wpdate( 'H:i:s', $timestamp );

		$this->assertEquals( '14:45:30', $result );
	}

	/**
	 * Test dianxiaomi_wpdate uses current time when no timestamp.
	 */
	public function test_dianxiaomi_wpdate_current_time(): void {
		$result = \dianxiaomi_wpdate( 'Y' );

		// Should be current year.
		$this->assertEquals( date( 'Y' ), $result );
	}

	/**
	 * Test dianxiaomi_wpdate returns string.
	 */
	public function test_dianxiaomi_wpdate_returns_string(): void {
		$result = \dianxiaomi_wpdate( 'Y-m-d H:i:s' );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}
}
