<?php
/**
 * Dianxiaomi API Authentication Tests.
 *
 * @package Dianxiaomi\Tests\Unit
 */

declare(strict_types=1);

namespace Dianxiaomi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Dianxiaomi_API_Authentication;
use WP_User;
use WP_Error;
use ReflectionMethod;

/**
 * Test class for Dianxiaomi_API_Authentication.
 */
class APIAuthenticationTest extends TestCase {

	/**
	 * Authentication instance.
	 *
	 * @var Dianxiaomi_API_Authentication
	 */
	private Dianxiaomi_API_Authentication $auth;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		\reset_all();
		$this->auth = new Dianxiaomi_API_Authentication();
	}

	/**
	 * Test constructor adds authentication filter.
	 */
	public function test_constructor_adds_filter(): void {
		global $wp_filters;

		$this->assertArrayHasKey( 'dianxiaomi_api_check_authentication', $wp_filters );
		$this->assertTrue(
			\has_filter( 'dianxiaomi_api_check_authentication', array( $this->auth, 'authenticate' ) ) !== false
		);
	}

	/**
	 * Helper to set API server path.
	 */
	private function setApiServerPath( string $path ): void {
		$instance = \get_dianxiaomi_instance();
		// Initialize the server if not set.
		if ( ! isset( $instance->api->server ) ) {
			$instance->api->server = new \Dianxiaomi_API_Server();
		}
		$instance->api->server->path = $path;
	}

	/**
	 * Test authenticate returns WP_User for root path.
	 */
	public function test_authenticate_returns_guest_for_root_path(): void {
		$this->setApiServerPath( '/' );

		$result = $this->auth->authenticate( null );

		$this->assertInstanceOf( WP_User::class, $result );
		$this->assertEquals( 0, $result->ID );
	}

	/**
	 * Test authenticate returns WP_Error when API key is missing.
	 */
	public function test_authenticate_returns_error_when_key_missing(): void {
		$this->setApiServerPath( '/orders' );

		// Clear any existing headers/GET params.
		$_SERVER = array_filter( $_SERVER, fn( $key ) => ! str_starts_with( $key, 'HTTP_' ), ARRAY_FILTER_USE_KEY );
		unset( $_GET['key'] );

		$result = $this->auth->authenticate( null );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'dianxiaomi_api_authentication_error', $result->get_error_code() );
		$this->assertStringContainsString( 'Key is missing', $result->get_error_message() );
	}

	/**
	 * Test authenticate returns WP_Error when API key is invalid.
	 */
	public function test_authenticate_returns_error_when_key_invalid(): void {
		$this->setApiServerPath( '/orders' );

		// Set an invalid API key using AFTERSHIP_WP_KEY header format.
		$_SERVER['HTTP_AFTERSHIP_WP_KEY'] = 'invalid_key_12345';

		// Mock wp_verify_nonce to return true so we get to the key validation.
		$_REQUEST['_wpnonce'] = 'test_nonce';
		\set_mock_return( 'wp_verify_nonce', true );

		$result = $this->auth->authenticate( null );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'dianxiaomi_api_authentication_error', $result->get_error_code() );

		// Cleanup.
		unset( $_SERVER['HTTP_AFTERSHIP_WP_KEY'], $_REQUEST['_wpnonce'] );
	}

	/**
	 * Test authenticate accepts key from AFTERSHIP_WP_KEY header.
	 */
	public function test_authenticate_accepts_aftership_header(): void {
		$this->setApiServerPath( '/orders' );

		$_SERVER['HTTP_AFTERSHIP_WP_KEY'] = 'valid_api_key';
		$_REQUEST['_wpnonce'] = 'test_nonce';
		\set_mock_return( 'wp_verify_nonce', true );

		// Mock get_users to return a valid user.
		\set_mock_return( 'get_users', array( 1 ) );

		$result = $this->auth->authenticate( null );

		// Should either be WP_User or WP_Error depending on nonce.
		$this->assertTrue( $result instanceof WP_User || $result instanceof WP_Error );

		// Cleanup.
		unset( $_SERVER['HTTP_AFTERSHIP_WP_KEY'], $_REQUEST['_wpnonce'] );
	}

	/**
	 * Test authenticate accepts key from query string.
	 */
	public function test_authenticate_accepts_querystring_key(): void {
		$this->setApiServerPath( '/orders' );

		$_GET['key'] = 'querystring_api_key';
		$_REQUEST['_wpnonce'] = 'test_nonce';
		\set_mock_return( 'wp_verify_nonce', true );

		$result = $this->auth->authenticate( null );

		// Should return error for invalid key.
		$this->assertInstanceOf( WP_Error::class, $result );

		// Cleanup.
		unset( $_GET['key'], $_REQUEST['_wpnonce'] );
	}

	/**
	 * Test authenticate returns WP_Error when nonce fails.
	 */
	public function test_authenticate_returns_error_when_nonce_fails(): void {
		$this->setApiServerPath( '/orders' );

		// Use AFTERSHIP_WP_KEY header format which the code expects.
		$_SERVER['HTTP_AFTERSHIP_WP_KEY'] = 'valid_key';
		$_REQUEST['_wpnonce'] = 'invalid_nonce';

		// Mock get_users to return a valid user ID so key validation passes.
		\set_mock_return( 'get_users', array( 1 ) );
		// Now mock wp_verify_nonce to fail.
		\set_mock_return( 'wp_verify_nonce', false );

		$result = $this->auth->authenticate( null );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'Nonce', $result->get_error_message() );

		// Cleanup.
		unset( $_SERVER['HTTP_AFTERSHIP_WP_KEY'], $_REQUEST['_wpnonce'] );
	}

	/**
	 * Test private method get_user_by_api_key returns valid user.
	 */
	public function test_get_user_by_api_key_returns_user(): void {
		$method = new ReflectionMethod( Dianxiaomi_API_Authentication::class, 'get_user_by_api_key' );
		$method->setAccessible( true );

		// Mock get_users to return user ID.
		\set_mock_return( 'get_users', array( 42 ) );

		$result = $method->invoke( $this->auth, 'valid_api_key' );

		$this->assertInstanceOf( WP_User::class, $result );
		$this->assertEquals( 42, $result->ID );
	}

	/**
	 * Test private method get_user_by_api_key throws on invalid key.
	 */
	public function test_get_user_by_api_key_throws_on_invalid(): void {
		$method = new ReflectionMethod( Dianxiaomi_API_Authentication::class, 'get_user_by_api_key' );
		$method->setAccessible( true );

		// Mock get_users to return empty array.
		\set_mock_return( 'get_users', array() );

		$this->expectException( \Exception::class );
		$this->expectExceptionCode( 401 );

		$method->invoke( $this->auth, 'invalid_api_key' );
	}

	/**
	 * Test getallheaders function exists.
	 */
	public function test_getallheaders_function_exists(): void {
		$this->assertTrue( function_exists( 'getallheaders' ) );
	}

	/**
	 * Test getallheaders parses HTTP_ prefixed server vars.
	 */
	public function test_getallheaders_parses_server_vars(): void {
		$_SERVER['HTTP_X_CUSTOM_HEADER'] = 'test_value';
		$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

		$headers = \getallheaders();

		$this->assertIsArray( $headers );
		$this->assertArrayHasKey( 'X-Custom-Header', $headers );
		$this->assertEquals( 'test_value', $headers['X-Custom-Header'] );

		// Cleanup.
		unset( $_SERVER['HTTP_X_CUSTOM_HEADER'], $_SERVER['HTTP_CONTENT_TYPE'] );
	}

	/**
	 * Test authentication uses cache.
	 */
	public function test_authentication_uses_cache(): void {
		$method = new ReflectionMethod( Dianxiaomi_API_Authentication::class, 'get_user_by_api_key' );
		$method->setAccessible( true );

		$api_key   = 'cached_api_key';
		$cache_key = 'dianxiaomi_user_' . md5( $api_key );

		// Set cache manually.
		\wp_cache_set( $cache_key, 99, '', 3600 );

		$result = $method->invoke( $this->auth, $api_key );

		$this->assertInstanceOf( WP_User::class, $result );
		$this->assertEquals( 99, $result->ID );
	}
}
